<?php

namespace App\Actions\VendorBill;

use App\Actions\Action;
use App\Actions\Concerns\PostsJournalEntries;
use App\Enums\JournalEntryStatus;
use App\Enums\VendorBillStatus;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Models\VendorBill;
use App\Services\AuditLogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VoidVendorBill
{
    use Action, PostsJournalEntries;

    public function handle(VendorBill $bill, User $voider, string $reason): VendorBill
    {
        return DB::transaction(function () use ($bill, $voider, $reason) {
            $bill = VendorBill::where('id', $bill->id)->lockForUpdate()->first();

            if ($bill->status === VendorBillStatus::Paid) {
                throw new \RuntimeException('Paid bills cannot be voided.');
            }

            if (! $bill->status->isVoidable()) {
                throw new \RuntimeException('Only draft bills can be voided.');
            }

            $previous = [
                'status' => $bill->status->value,
                'balance_due' => (float) $bill->balance_due,
            ];

            if (! $bill->isDraft() && (float) $bill->balance_due > 0.0001) {
                $this->reverseRemainingBalance($bill, $voider, $reason);
            }

            $bill->update([
                'status' => VendorBillStatus::Voided,
                'balance_due' => 0,
                'voided_at' => now(),
                'voided_by' => $voider->id,
                'void_reason' => $reason,
                'updated_by' => $voider->id,
            ]);

            app(AuditLogService::class)->record(
                $bill->organization,
                $voider->id,
                'vendor-bill.voided',
                VendorBill::class,
                $bill->id,
                $previous,
                ['status' => 'voided', 'balance_due' => 0, 'void_reason' => $reason]
            );

            return $bill->fresh()->load(['lines', 'journalEntry', 'vendor']);
        });
    }

    /**
     * Reverse the still-unpaid portion of a posted bill. The reversal is
     * created as a draft so it flows through the journal approval workflow,
     * mirroring ReverseJournalEntry, and the original entry is marked reversed.
     */
    protected function reverseRemainingBalance(VendorBill $bill, User $voider, string $reason): void
    {
        $entry = $bill->journalEntry()->with('journal')->first();

        if (! $entry || ! $entry->isPosted()) {
            throw new \RuntimeException('A posted journal entry is required to void a posted bill.');
        }

        $organization = $bill->organization;
        $total = (float) $entry->total_debit;
        $balance = round((float) $bill->balance_due, 4);

        if ($total <= 0) {
            throw new \RuntimeException('The bill journal entry carries no amount to reverse.');
        }

        $fraction = $balance / $total;

        $reversalDebits = $this->scaledReversalSide($entry, 'credit', $fraction);
        $reversalCredits = $this->scaledReversalSide($entry, 'debit', $fraction);

        if ($reversalDebits->isEmpty() || $reversalCredits->isEmpty()) {
            throw new \RuntimeException('The bill journal entry cannot be proportionally reversed.');
        }

        $reversal = JournalEntry::create([
            'organization_id' => $entry->organization_id,
            'journal_id' => $entry->journal_id,
            'fiscal_year_id' => $entry->fiscal_year_id,
            'accounting_period_id' => $entry->accounting_period_id,
            'entry_number' => $this->generateEntryNumber($organization, $entry->journal, now()),
            'entry_date' => now()->toDateString(),
            'description' => "Reversal of {$entry->entry_number}: {$reason}",
            'reference' => $entry->entry_number,
            'currency_id' => $entry->currency_id,
            'base_currency_id' => $entry->base_currency_id,
            'exchange_rate' => $entry->exchange_rate,
            'total_debit' => $balance,
            'total_credit' => $balance,
            'status' => JournalEntryStatus::Draft,
            'reversal_of_id' => $entry->id,
            'created_by' => $voider->id,
        ]);

        $this->createReversalLines($reversal, $reversalDebits, $balance, 'debit');
        $this->createReversalLines($reversal, $reversalCredits, $balance, 'credit');

        $entry->update([
            'status' => JournalEntryStatus::Reversed,
            'reversed_by' => $voider->id,
            'reversed_at' => now(),
            'reversal_reason' => $reason,
        ]);

        app(AuditLogService::class)->record(
            $organization,
            $voider->id,
            'journal-entry.reversed',
            JournalEntry::class,
            $entry->id,
            ['status' => 'posted'],
            ['status' => 'reversed', 'reversal_reason' => $reason]
        );
    }

    /**
     * Scale one side of the original entry lines proportionally to the
     * remaining balance. Original debits become reversal credits and vice
     * versa, so each side of the returned collection matches the reversal side.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function scaledReversalSide(JournalEntry $entry, string $originalSide, float $fraction)
    {
        return $entry->lines()
            ->orderBy('line_number')
            ->get(['account_id', 'description', 'debit', 'credit'])
            ->filter(fn ($line) => (float) $line->{$originalSide} > 0)
            ->map(fn ($line) => [
                'account_id' => $line->account_id,
                'description' => $line->description,
                'amount' => (float) $line->{$originalSide} * $fraction,
            ])
            ->values();
    }

    /**
     * Persist one side of the proportional reversal. The last line absorbs the
     * rounding remainder so the side totals the remaining balance exactly.
     *
     * @param  Collection<int, array<string, mixed>>  $lines
     */
    protected function createReversalLines(JournalEntry $reversal, $lines, float $balance, string $side): void
    {
        $lineNumber = (int) $reversal->lines()->max('line_number') + 1;
        $allocated = 0.0;
        $lastIndex = $lines->count() - 1;

        foreach ($lines as $index => $line) {
            $amount = $index === $lastIndex
                ? round($balance - $allocated, 4)
                : round($line['amount'], 4);

            $allocated = round($allocated + $amount, 4);

            JournalEntryLine::create([
                'organization_id' => $reversal->organization_id,
                'journal_entry_id' => $reversal->id,
                'account_id' => $line['account_id'],
                'line_number' => $lineNumber++,
                'description' => $line['description'],
                'debit' => $side === 'debit' ? $amount : 0.0,
                'credit' => $side === 'credit' ? $amount : 0.0,
                'currency_id' => $reversal->currency_id,
                'currency_amount' => $amount,
                'base_amount' => round($amount * (float) $reversal->exchange_rate, 4),
                'exchange_rate' => $reversal->exchange_rate,
                'reference' => $reversal->reference,
            ]);
        }
    }
}
