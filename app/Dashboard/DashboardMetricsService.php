<?php

namespace App\Dashboard;

use App\Enums\FiscalYearStatus;
use App\Enums\JournalEntryStatus;
use App\Models\JournalEntry;
use App\Models\Organization;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates the org-scoped dashboard KPIs (§7.40) in base currency.
 *
 * Results are cached per organization for five minutes and invalidated from
 * the JournalPosted event whenever a journal entry is posted.
 */
class DashboardMetricsService
{
    private const CACHE_TTL_SECONDS = 300;

    /**
     * @return array<string, mixed>
     */
    public function forOrganization(Organization $organization): array
    {
        $cacheKey = $this->cacheKey($organization);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($organization) {
            return $this->recompute($organization);
        });
    }

    public static function forgetForOrganization(int $organizationId): void
    {
        Cache::forget("dashboard:{$organizationId}:metrics");
    }

    /**
     * @return array<string, mixed>
     */
    protected function recompute(Organization $organization): array
    {
        $cash = $this->cashAndBankBalances($organization);
        $arAp = $this->accountsReceivableAndPayable($organization);
        $performance = $this->periodPerformance($organization);

        return [
            'currency' => $organization->baseCurrency->code,
            'cash_balance' => $cash['cash'],
            'bank_balance' => $cash['bank'],
            'accounts_receivable' => $arAp['receivable'],
            'accounts_payable' => $arAp['payable'],
            'outstanding_invoices' => $arAp['invoices'],
            'outstanding_bills' => $arAp['bills'],
            'invoices_overdue' => $arAp['invoices_overdue'],
            'bills_overdue' => $arAp['bills_overdue'],
            'revenue' => $performance['revenue'],
            'expenses' => $performance['expenses'],
            'net_income' => $performance['net_income'],
            'measurement_period' => $performance['period'],
            'recent_entries' => $this->recentEntries($organization),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{cash: float, bank: float}
     */
    protected function cashAndBankBalances(Organization $organization): array
    {
        $query = $this->postedBaseTotals($organization)
            ->join('accounts as accounts', 'accounts.id', '=', 'lines.account_id')
            ->where('accounts.is_cash_account', true);

        $cashAccounts = (clone $query)
            ->where(function (Builder $builder) {
                $builder->where('accounts.code', 'not ilike', '%bank%')->where('accounts.name', 'not ilike', '%bank%');
            })
            ->value('net');

        $bankAccounts = (clone $query)
            ->where(function (Builder $builder) {
                $builder->where('accounts.code', 'ilike', '%bank%')->orWhere('accounts.name', 'ilike', '%bank%');
            })
            ->value('net');

        return [
            'cash' => round((float) $cashAccounts, 4),
            'bank' => round((float) $bankAccounts, 4),
        ];
    }

    /**
     * @return array{receivable: float, payable: float, invoices: int, bills: int, invoices_overdue: int, bills_overdue: int}
     */
    protected function accountsReceivableAndPayable(Organization $organization): array
    {
        $today = now()->toDateString();

        $invoices = DB::table('customer_invoices')
            ->where('organization_id', $organization->id)
            ->whereNull('voided_at')
            ->whereIn('status', ['posted', 'partially_paid'])
            ->selectRaw('COALESCE(SUM(CASE WHEN balance_due > 0 THEN balance_due ELSE 0 END), 0) as total')
            ->selectRaw('COUNT(*) FILTER (WHERE balance_due > 0) as open')
            ->selectRaw('COUNT(*) FILTER (WHERE balance_due > 0 AND due_date < ?) as overdue', [$today])
            ->first();

        $bills = DB::table('vendor_bills')
            ->where('organization_id', $organization->id)
            ->whereNull('voided_at')
            ->selectRaw('COALESCE(SUM(CASE WHEN balance_due > 0 THEN balance_due ELSE 0 END), 0) as total')
            ->selectRaw('COUNT(*) FILTER (WHERE balance_due > 0) as open')
            ->selectRaw('COUNT(*) FILTER (WHERE balance_due > 0 AND due_date < ?) as overdue', [$today])
            ->first();

        return [
            'receivable' => round((float) $invoices->total, 4),
            'payable' => round((float) $bills->total, 4),
            'invoices' => (int) $invoices->open,
            'bills' => (int) $bills->open,
            'invoices_overdue' => (int) $invoices->overdue,
            'bills_overdue' => (int) $bills->overdue,
        ];
    }

    /**
     * @return array{revenue: float, expenses: float, net_income: float, period: string}
     */
    protected function periodPerformance(Organization $organization): array
    {
        $fiscalYear = $organization->fiscalYears()
            ->where('status', FiscalYearStatus::Open)
            ->orderBy('start_date')
            ->first();

        if ($fiscalYear) {
            $start = $fiscalYear->start_date;
            $end = $fiscalYear->end_date;
            $period = $fiscalYear->name;
        } else {
            $start = null;
            $end = null;
            $period = 'All time';
        }

        $rows = $this->postedBaseTotals($organization)
            ->join('accounts as accounts', 'accounts.id', '=', 'lines.account_id')
            ->join('account_types as account_types', 'account_types.id', '=', 'accounts.account_type_id')
            ->whereIn('account_types.category', ['revenue', 'expense'])
            ->when($start, fn (Builder $query) => $query->whereDate('entries.entry_date', '>=', $start))
            ->when($end, fn (Builder $query) => $query->whereDate('entries.entry_date', '<=', $end))
            ->groupBy('accounts.id', 'accounts.normal_balance', 'account_types.category')
            ->select([
                'accounts.id',
                'accounts.normal_balance',
                'account_types.category',
                DB::raw('SUM(CASE WHEN lines.debit > 0 THEN lines.base_amount ELSE 0 END) as total_debit'),
                DB::raw('SUM(CASE WHEN lines.credit > 0 THEN lines.base_amount ELSE 0 END) as total_credit'),
            ])
            ->get();

        $revenue = 0.0;
        $expenses = 0.0;

        foreach ($rows as $row) {
            $debit = (float) $row->total_debit;
            $credit = (float) $row->total_credit;
            $value = $row->normal_balance === 'credit' ? $credit - $debit : $debit - $credit;

            if ($row->category === 'revenue') {
                $revenue += $value;
            } else {
                $expenses += $value;
            }
        }

        return [
            'revenue' => round($revenue, 4),
            'expenses' => round($expenses, 4),
            'net_income' => round($revenue - $expenses, 4),
            'period' => $period,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function recentEntries(Organization $organization): array
    {
        return JournalEntry::forOrganization($organization)
            ->where('status', JournalEntryStatus::Posted)
            ->with(['journal', 'currency'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (JournalEntry $entry) => [
                'id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'entry_date' => $entry->entry_date->format('Y-m-d'),
                'description' => $entry->description,
                'journal' => $entry->journal ? ['code' => $entry->journal->code, 'name' => $entry->journal->name] : null,
                'total_debit' => (float) $entry->total_debit,
                'total_credit' => (float) $entry->total_credit,
            ])
            ->all();
    }

    /**
     * Net base-currency movement of posted lines (debits minus credits).
     */
    protected function postedBaseTotals(Organization $organization): Builder
    {
        return DB::table('journal_entries as entries')
            ->join('journal_entry_lines as lines', 'lines.journal_entry_id', '=', 'entries.id')
            ->where('entries.organization_id', $organization->id)
            ->where('entries.status', JournalEntryStatus::Posted->value)
            ->select(DB::raw('SUM(CASE WHEN lines.debit > 0 THEN lines.base_amount ELSE 0 END) - SUM(CASE WHEN lines.credit > 0 THEN lines.base_amount ELSE 0 END) as net'));
    }

    protected function cacheKey(Organization $organization): string
    {
        return "dashboard:{$organization->id}:metrics";
    }
}
