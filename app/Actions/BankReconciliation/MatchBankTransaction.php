<?php

namespace App\Actions\BankReconciliation;

use App\Actions\Action;
use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use App\Models\JournalEntryLine;
use App\Models\ReconciliationMatch;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class MatchBankTransaction
{
    use Action, MaintainsReconciliationState;

    public function handle(BankReconciliation $reconciliation, array $data, User $actor): ReconciliationMatch
    {
        return DB::transaction(function () use ($reconciliation, $data, $actor) {
            $locked = BankReconciliation::whereKey($reconciliation->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw new \RuntimeException('A finalized reconciliation cannot be modified. Reopen it first.');
            }

            $bankTransaction = BankTransaction::whereKey($data['bank_transaction_id'])->lockForUpdate()->firstOrFail();

            if ($bankTransaction->organization_id !== $locked->organization_id) {
                throw new \RuntimeException('The bank transaction must belong to the same organization.');
            }

            if ($bankTransaction->bank_account_id !== $locked->bank_account_id) {
                throw new \RuntimeException('The bank transaction must belong to the reconciled bank account.');
            }

            if ($bankTransaction->reconciliation_status === 'reconciled') {
                throw new \RuntimeException('This bank transaction already belongs to a finalized reconciliation.');
            }

            $journalEntryLine = $this->resolveJournalEntryLine($locked->load('bankAccount'), $data['journal_entry_line_id'] ?? null);
            $amount = bcadd((string) $data['matched_amount'], '0', 4);

            if (bccomp($amount, '0', 4) <= 0) {
                throw new \RuntimeException('The matched amount must be greater than zero.');
            }

            $transactionCapacity = $this->transactionCapacity($bankTransaction);

            if (bccomp($amount, $transactionCapacity, 4) > 0) {
                throw new \RuntimeException('The matched amount exceeds the un-matched bank transaction amount.');
            }

            $lineCapacity = $journalEntryLine ? $this->journalLineCapacity($journalEntryLine) : null;

            if ($lineCapacity !== null && bccomp($amount, $lineCapacity, 4) > 0) {
                throw new \RuntimeException('The matched amount exceeds the un-matched journal entry line amount.');
            }

            $match = ReconciliationMatch::create([
                'organization_id' => $locked->organization_id,
                'reconciliation_id' => $locked->id,
                'bank_transaction_id' => $bankTransaction->id,
                'journal_entry_line_id' => $journalEntryLine?->id,
                'matched_amount' => $amount,
                'match_type' => $this->deriveMatchType($amount, $transactionCapacity, $lineCapacity),
                'created_by' => $actor->id,
            ]);

            $bankTransaction->update(['reconciliation_status' => 'matched']);

            $this->recompute($locked);

            app(AuditLogService::class)->record(
                $locked->organization,
                $actor->id,
                'bank-reconciliation.matched',
                ReconciliationMatch::class,
                $match->id,
                null,
                $this->auditValues($match, $locked->isOutsideStatementPeriod($bankTransaction->transaction_date))
            );

            return $match;
        });
    }

    protected function resolveJournalEntryLine(BankReconciliation $reconciliation, mixed $journalEntryLineId): ?JournalEntryLine
    {
        if ($journalEntryLineId === null || $journalEntryLineId === '') {
            return null;
        }

        $journalEntryLine = JournalEntryLine::with('journalEntry')->findOrFail($journalEntryLineId);

        if ($journalEntryLine->organization_id !== $reconciliation->organization_id) {
            throw new \RuntimeException('The journal entry line must belong to the same organization.');
        }

        if (! $journalEntryLine->journalEntry?->isPosted()) {
            throw new \RuntimeException('Only posted journal entry lines can be reconciled.');
        }

        // The controller only offers lines on this account, but the guard must
        // hold for any request: a line on another account is never reconcilable.
        if ($journalEntryLine->account_id !== $reconciliation->bankAccount?->account_id) {
            throw new \RuntimeException('The journal entry line must belong to the reconciled bank account ledger.');
        }

        if ($journalEntryLine->currency_id !== $reconciliation->currency_id) {
            throw new \RuntimeException('The journal entry line currency must match the bank account currency.');
        }

        return $journalEntryLine;
    }

    protected function transactionCapacity(BankTransaction $bankTransaction): string
    {
        $matched = (string) ReconciliationMatch::where('bank_transaction_id', $bankTransaction->id)->sum('matched_amount');

        return $this->remaining($this->largerSide($bankTransaction->debit, $bankTransaction->credit), $matched);
    }

    protected function journalLineCapacity(JournalEntryLine $journalEntryLine): string
    {
        $matched = (string) ReconciliationMatch::where('journal_entry_line_id', $journalEntryLine->id)->sum('matched_amount');

        return $this->remaining($this->largerSide($journalEntryLine->debit, $journalEntryLine->credit), $matched);
    }

    /**
     * A row is either a debit or a credit, so the matchable side is whichever
     * is larger. Compared with bcmath rather than floats: these are
     * numeric(20,4) columns, and a float round-trip can make a fully consumed
     * row look like it has a fraction of a unit left.
     */
    protected function largerSide(mixed $debit, mixed $credit): string
    {
        $debit = (string) $debit;
        $credit = (string) $credit;

        return bccomp($debit, $credit, 4) >= 0 ? $debit : $credit;
    }

    /**
     * Remaining amount that may still be matched on one side of the
     * many-to-many relationship (INV-010 allocation safety, applied to matches).
     */
    protected function remaining(string $total, string $alreadyMatched): string
    {
        $remaining = bcsub($total, $alreadyMatched, 4);

        return bccomp($remaining, '0', 4) < 0 ? '0.0000' : $remaining;
    }

    /**
     * Derive the match type from how the link consumes each side, so the
     * classification always reflects the real split/combined structure.
     */
    protected function deriveMatchType(string $amount, string $transactionCapacity, ?string $lineCapacity): string
    {
        $consumesTransaction = bccomp($amount, $transactionCapacity, 4) === 0;

        if ($lineCapacity === null) {
            return $consumesTransaction ? 'exact' : 'partial';
        }

        return match (true) {
            $consumesTransaction && bccomp($amount, $lineCapacity, 4) === 0 => 'exact',
            $consumesTransaction => 'combined',
            default => 'split',
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function auditValues(ReconciliationMatch $match, bool $outOfPeriod = false): array
    {
        return $match->only([
            'reconciliation_id', 'bank_transaction_id', 'journal_entry_line_id',
            'matched_amount', 'match_type', 'created_by',
        ]) + ['out_of_period' => $outOfPeriod];
    }

    public function rules(): array
    {
        return [
            'bank_transaction_id' => ['required', 'exists:bank_transactions,id'],
            'journal_entry_line_id' => ['nullable', 'exists:journal_entry_lines,id'],
            'matched_amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
