<?php

namespace App\Reports;

use Illuminate\Support\Facades\DB;

/**
 * Cash flow statement report (§7.21).
 *
 * Version 1 uses account-based classification: accounts flagged with a
 * cash_flow_section (excluding cash accounts themselves) contribute their net
 * change to that section. The cash impact of a change follows the natural
 * indirect-method sign: increases in credit-normal accounts (revenue,
 * liabilities) are inflows, increases in debit-normal accounts (expenses,
 * assets) are outflows.
 */
final class CashFlowReport extends ReportQuery
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $start = $this->windowStart();
        $end = $this->windowEnd();

        $query = $this->postedLinesQuery()
            ->join('accounts as accounts', 'accounts.id', '=', 'lines.account_id')
            ->whereNotNull('accounts.cash_flow_section')
            ->where('accounts.is_cash_account', false)
            ->groupBy([
                'accounts.id',
                'accounts.code',
                'accounts.name',
                'accounts.normal_balance',
                'accounts.cash_flow_section',
            ])
            ->select([
                'accounts.id',
                'accounts.code',
                'accounts.name',
                'accounts.normal_balance',
                'accounts.cash_flow_section',
                DB::raw('SUM(lines.base_amount) as total_base'),
                DB::raw('SUM(CASE WHEN lines.debit > 0 THEN lines.base_amount ELSE 0 END) as total_debit'),
                DB::raw('SUM(CASE WHEN lines.credit > 0 THEN lines.base_amount ELSE 0 END) as total_credit'),
            ]);

        $this->forRange($query, $start, $end);

        $sections = [
            'operating' => [],
            'investing' => [],
            'financing' => [],
        ];

        foreach ($query->get() as $row) {
            $debit = (float) $row->total_debit;
            $credit = (float) $row->total_credit;
            $value = $row->normal_balance === 'credit' ? $credit - $debit : $debit - $credit;
            $amount = $row->normal_balance === 'credit' ? $value : -$value;

            $sections[$row->cash_flow_section][] = [
                'account' => [
                    'id' => (int) $row->id,
                    'code' => $row->code,
                    'name' => $row->name,
                ],
                'amount' => $this->round($amount),
            ];
        }

        $totals = [];

        foreach ($sections as $key => $items) {
            $totals[$key] = $this->round(
                array_sum(array_map(fn (array $item) => $item['amount'], $items))
            );
        }

        return [
            'sections' => $sections,
            'totals' => $totals,
            'net_cash_change' => $this->netCashChange($start, $end),
        ];
    }

    /**
     * Net change of all cash/bank accounts within the window, in base currency.
     */
    protected function netCashChange(?object $start, ?object $end): float
    {
        $query = $this->postedLinesQuery()
            ->join('accounts as accounts', 'accounts.id', '=', 'lines.account_id')
            ->where('accounts.is_cash_account', true)
            ->select(DB::raw('SUM(CASE WHEN lines.debit > 0 THEN lines.base_amount ELSE 0 END) - SUM(CASE WHEN lines.credit > 0 THEN lines.base_amount ELSE 0 END) as net'));

        $this->forRange($query, $start, $end);

        return $this->round((float) $query->value('net'));
    }
}
