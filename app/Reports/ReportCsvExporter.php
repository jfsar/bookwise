<?php

namespace App\Reports;

use App\Enums\ReportType;

/**
 * Flattens a report's build() result into tabular rows for CSV export.
 */
class ReportCsvExporter
{
    /**
     * @return array{headers: array<int, string>, rows: array<int, array<int, int|float|string|null>>}
     */
    public function export(ReportType $type, array $data): array
    {
        return match ($type) {
            ReportType::GeneralLedger => $this->generalLedger($data),
            ReportType::TrialBalance => $this->trialBalance($data),
            ReportType::IncomeStatement => $this->statement($data, 'Sections'),
            ReportType::BalanceSheet => $this->balanceSheet($data),
            ReportType::CashFlow => $this->cashFlow($data),
        };
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<int, int|float|string|null>>}
     */
    protected function generalLedger(array $data): array
    {
        $headers = ['Entry #', 'Date', 'Reference', 'Description', 'Journal', 'Account Code', 'Account Name', 'Debit', 'Credit', 'Running Balance'];
        $rows = [];

        foreach ($data['rows'] as $row) {
            $rows[] = [
                $row['entry_number'],
                $row['entry_date'],
                $row['reference'],
                $row['description'],
                $row['journal_code'],
                $row['account']['code'],
                $row['account']['name'],
                $row['debit'],
                $row['credit'],
                $row['running_balance'],
            ];
        }

        $rows[] = [null, null, null, null, null, null, 'Totals', $data['totals']['debit'], $data['totals']['credit'], null];

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<int, int|float|string|null>>}
     */
    protected function trialBalance(array $data): array
    {
        $headers = ['Account Code', 'Account Name', 'Opening Debit', 'Opening Credit', 'Period Debit', 'Period Credit', 'Closing Debit', 'Closing Credit'];
        $rows = [];

        foreach ($data['rows'] as $row) {
            $rows[] = [
                $row['account']['code'],
                $row['account']['name'],
                $row['opening_debit'],
                $row['opening_credit'],
                $row['period_debit'],
                $row['period_credit'],
                $row['closing_debit'],
                $row['closing_credit'],
            ];
        }

        $total = $data['totals'];
        $rows[] = [null, 'Totals', $total['opening_debit'], $total['opening_credit'], $total['period_debit'], $total['period_credit'], $total['closing_debit'], $total['closing_credit']];

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<int, int|float|string|null>>}
     */
    protected function statement(array $data, string $label): array
    {
        $headers = [$label, 'Account Code', 'Account Name', 'Amount'];
        $rows = [];

        foreach ($data['sections'] as $section => $trees) {
            $rows[] = [$this->title($section), null, null, null];
            $this->flatten($trees, '', $rows);
            $rows[] = [null, null, "Total {$this->title($section)}", $data['totals'][$section]];
        }

        $rows[] = [null, null, 'Net Income', $data['totals']['net_income']];

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<int, int|float|string|null>>}
     */
    protected function balanceSheet(array $data): array
    {
        $headers = ['Section', 'Account Code', 'Account Name', 'Amount'];
        $rows = [];

        foreach (['assets' => 'Assets', 'liabilities' => 'Liabilities', 'equity' => 'Equity'] as $key => $section) {
            $rows[] = [$section, null, null, null];
            $this->flatten($data['sections'][$key], '', $rows);
            $rows[] = [null, null, "Total {$section}", $data['totals'][$key]];
        }

        $rows[] = [null, null, 'Retained earnings (to date)', $data['retained_earnings']];
        $rows[] = [null, null, 'Total Equity (incl. retained earnings)', $data['totals']['equity']];

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<int, int|float|string|null>>}
     */
    protected function cashFlow(array $data): array
    {
        $headers = ['Section', 'Account Code', 'Account Name', 'Amount'];
        $rows = [];

        foreach (['operating' => 'Operating Activities', 'investing' => 'Investing Activities', 'financing' => 'Financing Activities'] as $key => $section) {
            $rows[] = [$section, null, null, null];

            foreach ($data['sections'][$key] as $item) {
                $rows[] = [null, $item['account']['code'], $item['account']['name'], $item['amount']];
            }

            $rows[] = [null, null, "Net cash - {$section}", $data['totals'][$key]];
        }

        $rows[] = [null, null, 'Net change in cash accounts', $data['net_cash_change']];

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param  array<int, array<string, mixed>>  $trees
     * @param  array<int, array<int, int|float|string|null>>  $rows
     */
    protected function flatten(array $trees, string $indent, array &$rows): void
    {
        foreach ($trees as $node) {
            $name = $indent.$node['name'].($node['children'] ? '' : '');
            $rows[] = [$indent ? '  ' : null, $node['code'], $name, $node['total']];

            $this->flatten($node['children'], $indent.'  ', $rows);
        }
    }

    protected function title(string $key): string
    {
        return match ($key) {
            'assets' => 'Assets',
            'liabilities' => 'Liabilities',
            'equity' => 'Equity',
            'revenue' => 'Revenue',
            'expenses' => 'Expenses',
            default => ucwords(str_replace('_', ' ', $key)),
        };
    }
}
