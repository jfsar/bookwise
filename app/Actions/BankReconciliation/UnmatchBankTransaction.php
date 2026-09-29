<?php

namespace App\Actions\BankReconciliation;

use App\Actions\Action;
use App\Models\BankReconciliation;
use App\Models\ReconciliationMatch;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class UnmatchBankTransaction
{
    use Action, MaintainsReconciliationState;

    public function handle(BankReconciliation $reconciliation, ReconciliationMatch $match, mixed $actor): void
    {
        DB::transaction(function () use ($reconciliation, $match, $actor) {
            $locked = BankReconciliation::whereKey($reconciliation->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw new \RuntimeException('A finalized reconciliation cannot be modified. Reopen it first.');
            }

            $lockedMatch = ReconciliationMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();

            if ($lockedMatch->reconciliation_id !== $locked->id) {
                throw new \RuntimeException('The match does not belong to this reconciliation.');
            }

            $auditValues = $lockedMatch->only([
                'reconciliation_id', 'bank_transaction_id', 'journal_entry_line_id',
                'matched_amount', 'match_type', 'created_by',
            ]);

            $bankTransactionId = $lockedMatch->bank_transaction_id;

            $lockedMatch->delete();

            $this->refreshTransactionStatus($bankTransactionId);

            $this->recompute($locked);

            app(AuditLogService::class)->record(
                $locked->organization,
                $actor?->id,
                'bank-reconciliation.unmatched',
                ReconciliationMatch::class,
                $lockedMatch->id,
                $auditValues,
                null
            );
        });
    }
}
