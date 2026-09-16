<?php

namespace App\Reports;

use App\Enums\JournalEntryStatus;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

abstract class ReportQuery
{
    protected readonly Organization $organization;

    /** @var array<string, mixed> */
    protected readonly array $filters;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(Organization $organization, array $filters = [])
    {
        $this->organization = $organization;
        $this->filters = $filters;
    }

    /**
     * Base query over posted journal-entry lines for the organization,
     * filtered by the shared scalar filters (account, journal, period,
     * fiscal year, reference). Date bounds are applied explicitly by each
     * report via {@see forRange()}.
     */
    protected function postedLinesQuery(): Builder
    {
        return DB::table('journal_entry_lines as lines')
            ->join('journal_entries as entries', fn (JoinClause $join) => $join
                ->on('entries.id', '=', 'lines.journal_entry_id')
                ->where('entries.organization_id', $this->organization->id)
                ->where('entries.status', JournalEntryStatus::Posted->value))
            ->where('lines.organization_id', $this->organization->id)
            ->when(! empty($this->filters['account_id']), fn (Builder $query) => $query->where('lines.account_id', $this->filters['account_id']))
            ->when(! empty($this->filters['journal_id']), fn (Builder $query) => $query->where('entries.journal_id', $this->filters['journal_id']))
            ->when(! empty($this->filters['fiscal_year_id']), fn (Builder $query) => $query->where('entries.fiscal_year_id', $this->filters['fiscal_year_id']))
            ->when(! empty($this->filters['accounting_period_id']), fn (Builder $query) => $query->where('entries.accounting_period_id', $this->filters['accounting_period_id']))
            ->when(! empty($this->filters['reference']), fn (Builder $query) => $query->where('entries.reference', 'ilike', '%'.$this->filters['reference'].'%'));
    }

    /**
     * Constrain a posted-lines query to an inclusive date range on the entry date.
     */
    protected function forRange(Builder $query, ?Carbon $start, ?Carbon $end): Builder
    {
        if ($start !== null) {
            $query->whereDate('entries.entry_date', '>=', $start->toDateString());
        }

        if ($end !== null) {
            $query->whereDate('entries.entry_date', '<=', $end->toDateString());
        }

        return $query;
    }

    /**
     * Lower bound of the reporting period, derived from the selected
     * accounting period, fiscal year, or explicit start date.
     */
    protected function windowStart(): ?Carbon
    {
        if (! empty($this->filters['accounting_period_id'])) {
            return AccountingPeriod::find($this->filters['accounting_period_id'])?->start_date;
        }

        if (! empty($this->filters['fiscal_year_id'])) {
            return FiscalYear::find($this->filters['fiscal_year_id'])?->start_date;
        }

        return $this->dateFilter('start_date');
    }

    /**
     * Upper bound of the reporting period, derived from the selected
     * accounting period, fiscal year, or explicit end date.
     */
    protected function windowEnd(): ?Carbon
    {
        if (! empty($this->filters['accounting_period_id'])) {
            return AccountingPeriod::find($this->filters['accounting_period_id'])?->end_date;
        }

        if (! empty($this->filters['fiscal_year_id'])) {
            return FiscalYear::find($this->filters['fiscal_year_id'])?->end_date;
        }

        return $this->dateFilter('end_date');
    }

    protected function dateFilter(string $key): ?Carbon
    {
        $value = $this->filters[$key] ?? null;

        if (! $value) {
            return null;
        }

        return Carbon::parse($value);
    }

    protected function round(float $value): float
    {
        return round($value, 4);
    }

    /**
     * Whether the report's build result contains any records to display or export.
     *
     * @param  array<string, mixed>  $data
     */
    public function hasRecords(array $data): bool
    {
        if (array_key_exists('rows', $data)) {
            return $data['rows'] !== [];
        }

        foreach ($data['sections'] ?? [] as $section) {
            if ($section !== []) {
                return true;
            }
        }

        return false;
    }
}
