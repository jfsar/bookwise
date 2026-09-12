<?php

namespace App\Reports;

/**
 * Balance sheet report (§7.20).
 *
 * Assets and Liabilities plus Equity as of the reporting end date, using
 * cumulative posted balances. Retained earnings carry accumulated net income
 * up to the reporting date so the statement enforces Assets = Liabilities +
 * Equity.
 */
final class BalanceSheetReport extends ReportQuery
{
    use AccountActivity;

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $end = $this->windowEnd();

        $activity = $this->activityByAccount(
            ['asset', 'liability', 'equity', 'revenue', 'expense'],
            null,
            $end
        );

        $assetAccounts = [];
        $liabilityAccounts = [];
        $equityAccounts = [];
        $amounts = [];
        $assetTotal = 0.0;
        $liabilityTotal = 0.0;
        $equityTotal = 0.0;
        $revenueTotal = 0.0;
        $expenseTotal = 0.0;

        foreach ($activity as $row) {
            $value = $this->normalBalanceValue($row);

            switch ($row->category) {
                case 'asset':
                    $assetAccounts[] = $this->treeAccount($row);
                    $amounts[(int) $row->id] = $value;
                    $assetTotal += $value;
                    break;
                case 'liability':
                    $liabilityAccounts[] = $this->treeAccount($row);
                    $amounts[(int) $row->id] = $value;
                    $liabilityTotal += $value;
                    break;
                case 'equity':
                    $equityAccounts[] = $this->treeAccount($row);
                    $amounts[(int) $row->id] = $value;
                    $equityTotal += $value;
                    break;
                case 'revenue':
                    $revenueTotal += $value;
                    break;
                case 'expense':
                    $expenseTotal += $value;
                    break;
            }
        }

        $retainedEarnings = $this->round($revenueTotal - $expenseTotal);
        $equityGrandTotal = $this->round($equityTotal + $retainedEarnings);

        return [
            'sections' => [
                'assets' => AccountTree::build($assetAccounts, $amounts),
                'liabilities' => AccountTree::build($liabilityAccounts, $amounts),
                'equity' => AccountTree::build($equityAccounts, $amounts),
            ],
            'retained_earnings' => $retainedEarnings,
            'totals' => [
                'assets' => $this->round($assetTotal),
                'liabilities' => $this->round($liabilityTotal),
                'equity' => $equityGrandTotal,
            ],
            'balanced' => abs($assetTotal - ($liabilityTotal + $equityGrandTotal)) < 0.01,
        ];
    }
}
