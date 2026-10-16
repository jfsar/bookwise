<?php

namespace App\Actions\Concerns;

use App\Models\Account;
use App\Models\Organization;
use App\Models\RecurringJournal;
use App\Models\RecurringJournalLine;
use Illuminate\Support\Collection;

trait ValidatesRecurringJournalLines
{
    /**
     * Validate recurring journal lines against the organization and return
     * them as a debit/credit keyed array.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array{account_id: int, description: ?string, debit: float, credit: float}>
     */
    protected function validatedLines(array $lines, Organization $organization): array
    {
        if (count($lines) < 2) {
            throw new \RuntimeException('A recurring journal needs at least two lines.');
        }

        $accounts = Account::forOrganization($organization)
            ->whereIn('id', collect($lines)->pluck('account_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $validated = [];

        foreach ($lines as $index => $line) {
            $account = $accounts->get($line['account_id'] ?? null);

            if (! $account) {
                throw new \RuntimeException('Every recurring journal line must use an account from the same organization.');
            }

            if (! $account->canReceiveJournalLines()) {
                throw new \RuntimeException("Account {$account->code} must be an active posting account.");
            }

            $debit = round((float) ($line['debit'] ?? 0), 4);
            $credit = round((float) ($line['credit'] ?? 0), 4);

            if ($debit < 0 || $credit < 0) {
                throw new \RuntimeException('Recurring journal amounts cannot be negative.');
            }

            if ($debit > 0 && $credit > 0) {
                throw new \RuntimeException('A recurring journal line cannot be both a debit and a credit.');
            }

            if ($debit == 0 && $credit == 0) {
                throw new \RuntimeException('A recurring journal line needs a debit or a credit amount.');
            }

            $totalDebit += $debit;
            $totalCredit += $credit;

            $validated[] = [
                'account_id' => $account->id,
                'description' => $this->nullableText($line['description'] ?? null),
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        if (abs($totalDebit - $totalCredit) >= 0.005) {
            throw new \RuntimeException('Recurring journal lines must balance. Debits must equal credits.');
        }

        return $validated;
    }

    /**
     * Replace the definition lines. Definitions are configuration, not ledger
     * facts, so existing executions keep pointing at the entries they created.
     *
     * @param  array<int, array{account_id: int, description: ?string, debit: float, credit: float}>  $lines
     */
    protected function replaceLines(RecurringJournal $recurringJournal, array $lines): void
    {
        $recurringJournal->lines()->delete();

        foreach (collect($lines)->values() as $index => $line) {
            RecurringJournalLine::create([
                'organization_id' => $recurringJournal->organization_id,
                'recurring_journal_id' => $recurringJournal->id,
                'account_id' => $line['account_id'],
                'line_number' => $index + 1,
                'description' => $line['description'],
                'debit' => $line['debit'],
                'credit' => $line['credit'],
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    protected function totals(Collection|array $lines): array
    {
        $debit = 0.0;
        $credit = 0.0;

        foreach ($lines as $line) {
            $debit += (float) $line['debit'];
            $credit += (float) $line['credit'];
        }

        return ['debit' => round($debit, 4), 'credit' => round($credit, 4)];
    }

    protected function nullableText(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
