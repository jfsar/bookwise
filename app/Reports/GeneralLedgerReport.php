<?php

namespace App\Reports;

use App\Models\Account;
use Carbon\Carbon;

/**
 * General ledger report (§7.17).
 *
 * Derives the ledger from posted journal lines, with an optional account
 * filter, and computes a running balance per account in the base currency.
 */
final class GeneralLedgerReport extends ReportQuery
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $start = $this->windowStart();
        $end = $this->windowEnd();

        $rows = $this->postedLinesQuery()
            ->join('accounts as accounts', 'accounts.id', '=', 'lines.account_id')
            ->leftJoin('journals as journals', 'journals.id', '=', 'entries.journal_id')
            ->pipe(fn ($query) => $this->forRange($query, $start, $end))
            ->orderBy('accounts.code')
            ->orderBy('entries.entry_date')
            ->orderBy('entries.id')
            ->orderBy('lines.line_number')
            ->get([
                'entries.id as entry_id',
                'entries.entry_number',
                'entries.entry_date',
                'entries.reference',
                'entries.description as entry_description',
                'journals.code as journal_code',
                'lines.id as line_id',
                'lines.description as line_description',
                'lines.debit as line_debit',
                'lines.credit as line_credit',
                'lines.base_amount',
                'accounts.id as account_id',
                'accounts.code as account_code',
                'accounts.name as account_name',
                'accounts.normal_balance',
            ])
            ->all();

        $running = [];
        $output = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($rows as $row) {
            $accountId = (int) $row->account_id;
            $base = (float) $row->base_amount;
            $debit = (float) $row->line_debit > 0 ? $base : 0.0;
            $credit = (float) $row->line_credit > 0 ? $base : 0.0;

            $net = $debit - $credit;
            $running[$accountId] = ($running[$accountId] ?? 0.0) + ($row->normal_balance === 'credit' ? -$net : $net);

            $totalDebit += $debit;
            $totalCredit += $credit;

            $output[] = [
                'line_id' => (int) $row->line_id,
                'entry_id' => (int) $row->entry_id,
                'entry_number' => $row->entry_number,
                'entry_date' => Carbon::parse($row->entry_date)->format('Y-m-d'),
                'reference' => $row->reference,
                'description' => $row->line_description ?? $row->entry_description,
                'journal_code' => $row->journal_code,
                'debit' => $this->round($debit),
                'credit' => $this->round($credit),
                'running_balance' => $this->round($running[$accountId]),
                'account' => [
                    'id' => $accountId,
                    'code' => $row->account_code,
                    'name' => $row->account_name,
                ],
            ];
        }

        return [
            'rows' => $output,
            'totals' => [
                'debit' => $this->round($totalDebit),
                'credit' => $this->round($totalCredit),
            ],
            'account' => $this->selectedAccount(),
        ];
    }

    /**
     * @return array{id: int, code: string, name: string}|null
     */
    protected function selectedAccount(): ?array
    {
        if (empty($this->filters['account_id'])) {
            return null;
        }

        $account = Account::find($this->filters['account_id']);

        if (! $account || $account->organization_id !== $this->organization->id) {
            return null;
        }

        return [
            'id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
        ];
    }
}
