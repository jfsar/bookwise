<?php

namespace App\Reports;

use App\Models\Account;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Trial balance report (§7.18).
 *
 * Shows opening, period and closing balances per account. Debits and credits
 * are expressed in base currency; a balanced trial balance has equal debit and
 * credit totals across all accounts.
 */
final class TrialBalanceReport extends ReportQuery
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $start = $this->windowStart();
        $end = $this->windowEnd();

        $accountIds = $this->affectedAccountIds($end);

        if ($accountIds->isEmpty()) {
            return $this->emptyResult();
        }

        $accountIndex = $this->loadAccounts($accountIds);
        $periodTotals = $this->sumByAccount($accountIds, $start, $end);
        $openingTotals = $start !== null
            ? $this->sumByAccount($accountIds, null, $start->copy()->subDay())
            : collect();

        $rows = [];
        $totals = [
            'opening_debit' => 0.0,
            'opening_credit' => 0.0,
            'period_debit' => 0.0,
            'period_credit' => 0.0,
            'closing_debit' => 0.0,
            'closing_credit' => 0.0,
        ];

        foreach ($accountIndex as $account) {
            $id = (int) $account->id;
            $period = $periodTotals->get($id, ['debit' => 0.0, 'credit' => 0.0]);
            $opening = $openingTotals->get($id, ['debit' => 0.0, 'credit' => 0.0]);

            $openingNet = $opening['debit'] - $opening['credit'];
            $periodDebit = $period['debit'];
            $periodCredit = $period['credit'];
            $closingNet = $openingNet + $periodDebit - $periodCredit;

            $openingDebit = $openingNet > 0 ? $openingNet : 0.0;
            $openingCredit = $openingNet < 0 ? -$openingNet : 0.0;
            $closingDebit = $closingNet > 0 ? $closingNet : 0.0;
            $closingCredit = $closingNet < 0 ? -$closingNet : 0.0;

            $rows[] = [
                'account' => [
                    'id' => $id,
                    'code' => $account->code,
                    'name' => $account->name,
                ],
                'opening_debit' => $this->round($openingDebit),
                'opening_credit' => $this->round($openingCredit),
                'period_debit' => $this->round($periodDebit),
                'period_credit' => $this->round($periodCredit),
                'closing_debit' => $this->round($closingDebit),
                'closing_credit' => $this->round($closingCredit),
            ];

            $totals['opening_debit'] += $openingDebit;
            $totals['opening_credit'] += $openingCredit;
            $totals['period_debit'] += $periodDebit;
            $totals['period_credit'] += $periodCredit;
            $totals['closing_debit'] += $closingDebit;
            $totals['closing_credit'] += $closingCredit;
        }

        array_walk($totals, fn (float &$value) => $value = $this->round($value));

        return [
            'rows' => $rows,
            'totals' => $totals,
            'balanced' => abs($totals['closing_debit'] - $totals['closing_credit']) < 0.01,
        ];
    }

    /**
     * Accounts that carry any posted activity up to the reporting end date.
     */
    protected function affectedAccountIds(?object $end): Collection
    {
        $query = $this->postedLinesQuery()
            ->join('accounts as accounts', 'accounts.id', '=', 'lines.account_id')
            ->distinct()
            ->select('accounts.id');

        if ($end !== null) {
            $this->forRange($query, null, $end);
        }

        return collect($query->pluck('id'))->map(fn ($id) => (int) $id);
    }

    protected function loadAccounts(Collection $ids): Collection
    {
        return Account::whereIn('id', $ids)
            ->where('organization_id', $this->organization->id)
            ->orderBy('code')
            ->get();
    }

    /**
     * Base-currency debit and credit sums per account within the given range.
     *
     * @return Collection<int, array{debit: float, credit: float}>
     */
    protected function sumByAccount(Collection $ids, ?object $start, ?object $end): Collection
    {
        $query = $this->postedLinesQuery()
            ->whereIn('lines.account_id', $ids)
            ->groupBy('lines.account_id')
            ->select([
                'lines.account_id',
                DB::raw('SUM(CASE WHEN lines.debit > 0 THEN lines.base_amount ELSE 0 END) as total_debit'),
                DB::raw('SUM(CASE WHEN lines.credit > 0 THEN lines.base_amount ELSE 0 END) as total_credit'),
            ]);

        $this->forRange($query, $start, $end);

        return collect($query->get())->mapWithKeys(function ($row) {
            return [(int) $row->account_id => [
                'debit' => (float) $row->total_debit,
                'credit' => (float) $row->total_credit,
            ]];
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyResult(): array
    {
        return [
            'rows' => [],
            'totals' => [
                'opening_debit' => 0.0,
                'opening_credit' => 0.0,
                'period_debit' => 0.0,
                'period_credit' => 0.0,
                'closing_debit' => 0.0,
                'closing_credit' => 0.0,
            ],
            'balanced' => true,
        ];
    }
}
