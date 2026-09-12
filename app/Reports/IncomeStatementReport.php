<?php

namespace App\Reports;

/**
 * Income statement report (§7.19).
 *
 * Revenue and expense activity for the reporting period, grouped according to
 * the account hierarchy, with net income = revenue − expenses.
 */
final class IncomeStatementReport extends ReportQuery
{
    use AccountActivity;

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $start = $this->windowStart();
        $end = $this->windowEnd();

        $activity = $this->activityByAccount(['revenue', 'expense'], $start, $end);

        $revenueAccounts = [];
        $expenseAccounts = [];
        $amounts = [];
        $revenueTotal = 0.0;
        $expenseTotal = 0.0;

        foreach ($activity as $row) {
            $value = $this->normalBalanceValue($row);
            $amounts[(int) $row->id] = $value;
            $account = $this->treeAccount($row);

            if ($row->category === 'revenue') {
                $revenueAccounts[] = $account;
                $revenueTotal += $value;
            } else {
                $expenseAccounts[] = $account;
                $expenseTotal += $value;
            }
        }

        return [
            'sections' => [
                'revenue' => AccountTree::build($revenueAccounts, $amounts),
                'expenses' => AccountTree::build($expenseAccounts, $amounts),
            ],
            'totals' => [
                'revenue' => $this->round($revenueTotal),
                'expenses' => $this->round($expenseTotal),
                'net_income' => $this->round($revenueTotal - $expenseTotal),
            ],
        ];
    }
}
