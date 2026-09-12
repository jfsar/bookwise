<?php

namespace App\Reports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared aggregate query for statements that group posted activity by account.
 */
trait AccountActivity
{
    /**
     * Base-currency debit/credit totals per account for the given categories.
     *
     * @param  string[]  $categories
     * @return Collection<int, object>
     */
    protected function activityByAccount(array $categories, ?Carbon $start, ?Carbon $end): Collection
    {
        $query = $this->postedLinesQuery()
            ->join('accounts as accounts', 'accounts.id', '=', 'lines.account_id')
            ->join('account_types as account_types', 'account_types.id', '=', 'accounts.account_type_id')
            ->whereIn('account_types.category', $categories)
            ->groupBy([
                'accounts.id',
                'accounts.parent_id',
                'accounts.code',
                'accounts.name',
                'accounts.normal_balance',
                'account_types.category',
            ])
            ->select([
                'accounts.id',
                'accounts.parent_id',
                'accounts.code',
                'accounts.name',
                'accounts.normal_balance',
                'account_types.category',
                DB::raw('SUM(CASE WHEN lines.debit > 0 THEN lines.base_amount ELSE 0 END) as total_debit'),
                DB::raw('SUM(CASE WHEN lines.credit > 0 THEN lines.base_amount ELSE 0 END) as total_credit'),
            ]);

        $this->forRange($query, $start, $end);

        return collect($query->get());
    }

    /**
     * Net balance of a single account row expressed with a positive sign in
     * the direction of its normal balance.
     */
    protected function normalBalanceValue(object $row): float
    {
        $debit = (float) $row->total_debit;
        $credit = (float) $row->total_credit;

        return $row->normal_balance === 'credit' ? $credit - $debit : $debit - $credit;
    }

    /**
     * @return array{id: int, parent_id: int|null, code: string, name: string, normal_balance: string}
     */
    protected function treeAccount(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'parent_id' => $row->parent_id !== null ? (int) $row->parent_id : null,
            'code' => $row->code,
            'name' => $row->name,
            'normal_balance' => $row->normal_balance,
        ];
    }
}
