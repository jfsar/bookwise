<?php

namespace App\Actions\JournalEntry;

use App\Actions\Action;
use App\Enums\JournalEntryStatus;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class ReverseJournalEntry
{
    use Action;

    public function handle(JournalEntry $entry, User $reverser, string $reason): JournalEntry
    {
        return DB::transaction(function () use ($entry, $reverser, $reason) {
            if (! $entry->isPosted()) {
                throw new \RuntimeException('Only posted entries can be reversed.');
            }

            $lines = $entry->lines()->get();

            $reversalEntry = JournalEntry::create([
                'organization_id' => $entry->organization_id,
                'journal_id' => $entry->journal_id,
                'fiscal_year_id' => $entry->fiscal_year_id,
                'accounting_period_id' => $entry->accounting_period_id,
                'entry_number' => $this->generateReversalNumber($entry),
                'entry_date' => now()->toDateString(),
                'description' => "Reversal of {$entry->entry_number}: {$reason}",
                'reference' => $entry->entry_number,
                'currency_id' => $entry->currency_id,
                'base_currency_id' => $entry->base_currency_id,
                'exchange_rate' => $entry->exchange_rate,
                'total_debit' => $entry->total_credit,
                'total_credit' => $entry->total_debit,
                'status' => JournalEntryStatus::Draft,
                'reversal_of_id' => $entry->id,
                'created_by' => $reverser->id,
            ]);

            $lineNumber = 1;
            foreach ($lines as $line) {
                JournalEntryLine::create([
                    'organization_id' => $line->organization_id,
                    'journal_entry_id' => $reversalEntry->id,
                    'account_id' => $line->account_id,
                    'line_number' => $lineNumber++,
                    'description' => $line->description,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'currency_id' => $line->currency_id,
                    'currency_amount' => $line->currency_amount,
                    'base_amount' => $line->base_amount,
                    'exchange_rate' => $line->exchange_rate,
                    'reference' => $line->reference,
                ]);
            }

            $entry->update([
                'status' => JournalEntryStatus::Reversed,
                'reversed_by' => $reverser->id,
                'reversed_at' => now(),
                'reversal_reason' => $reason,
            ]);

            app(AuditLogService::class)->record(
                $entry->organization,
                $reverser->id,
                'journal-entry.reversed',
                JournalEntry::class,
                $entry->id,
                ['status' => 'posted'],
                ['status' => 'reversed']
            );

            return $reversalEntry;
        });
    }

    protected function generateReversalNumber(JournalEntry $entry): string
    {
        $prefix = $entry->journal->code;
        $year = now()->format('Y');
        $sequence = JournalEntry::where('organization_id', $entry->organization_id)
            ->where('journal_id', $entry->journal_id)
            ->whereYear('entry_date', $year)
            ->count() + 1;

        return "{$prefix}-{$year}-".str_pad($sequence, 6, '0', STR_PAD_LEFT);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
