<?php

namespace App\Actions\Concerns;

use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Organization;
use Carbon\Carbon;

trait PostsJournalEntries
{
    protected function resolveJournal(Organization $organization): Journal
    {
        $journal = Journal::forOrganization($organization)
            ->active()
            ->where('code', 'GJ')
            ->first();

        if (! $journal) {
            $journal = Journal::forOrganization($organization)->active()->first();
        }

        if (! $journal) {
            throw new \RuntimeException('No active journal exists for this organization.');
        }

        return $journal;
    }

    /**
     * @return array{0: FiscalYear, 1: AccountingPeriod}
     */
    protected function resolvePeriod(Organization $organization, Carbon $date, string $dateLabel): array
    {
        $fiscalYear = FiscalYear::forOrganization($organization)
            ->open()
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();

        if (! $fiscalYear) {
            throw new \RuntimeException("No open fiscal year covers the {$dateLabel}.");
        }

        $period = AccountingPeriod::forOrganization($organization)
            ->open()
            ->where('fiscal_year_id', $fiscalYear->id)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();

        if (! $period) {
            throw new \RuntimeException("No open accounting period covers the {$dateLabel}.");
        }

        return [$fiscalYear, $period];
    }

    /**
     * Serialize a journal-sequence number by locking the journal row so
     * concurrent postings cannot mint the same entry number (SRS JE-004).
     */
    protected function generateEntryNumber(Organization $organization, Journal $journal, Carbon $date): string
    {
        Journal::where('id', $journal->id)->lockForUpdate()->first();

        $sequence = JournalEntry::where('organization_id', $organization->id)
            ->where('journal_id', $journal->id)
            ->whereYear('entry_date', $date->year)
            ->count() + 1;

        return "{$journal->code}-{$date->year}-".str_pad($sequence, 6, '0', STR_PAD_LEFT);
    }
}
