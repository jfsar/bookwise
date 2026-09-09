<?php

namespace App\Actions\JournalEntry;

use App\Actions\Action;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateJournalEntry
{
    use Action;

    public function handle(array $data, Organization $organization, User $creator): JournalEntry
    {
        return DB::transaction(function () use ($data, $organization, $creator) {
            $journal = Journal::findOrFail($data['journal_id']);
            $currency = Currency::findOrFail($data['currency_id']);
            $fiscalYear = FiscalYear::findOrFail($data['fiscal_year_id']);
            $period = AccountingPeriod::findOrFail($data['accounting_period_id']);

            $exchangeRate = (float) ($data['exchange_rate'] ?? 1);
            $totalDebit = 0.0;
            $totalCredit = 0.0;

            foreach ($data['lines'] as $lineData) {
                $totalDebit += (float) ($lineData['debit'] ?? 0);
                $totalCredit += (float) ($lineData['credit'] ?? 0);
            }

            $entryNumber = $this->generateEntryNumber($organization, $journal);

            $entry = JournalEntry::create([
                'organization_id' => $organization->id,
                'journal_id' => $journal->id,
                'fiscal_year_id' => $fiscalYear->id,
                'accounting_period_id' => $period->id,
                'entry_number' => $entryNumber,
                'entry_date' => $data['entry_date'],
                'description' => $data['description'],
                'reference' => $data['reference'] ?? null,
                'currency_id' => $currency->id,
                'base_currency_id' => $organization->base_currency_id,
                'exchange_rate' => $exchangeRate,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'status' => JournalEntryStatus::Draft,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'created_by' => $creator->id,
            ]);

            $lineNumber = 1;
            foreach ($data['lines'] as $lineData) {
                $account = Account::findOrFail($lineData['account_id']);
                $amount = (float) ($lineData['debit'] ?? $lineData['credit'] ?? 0);

                JournalEntryLine::create([
                    'organization_id' => $organization->id,
                    'journal_entry_id' => $entry->id,
                    'account_id' => $account->id,
                    'line_number' => $lineNumber++,
                    'description' => $lineData['description'] ?? null,
                    'debit' => $lineData['debit'] ?? 0,
                    'credit' => $lineData['credit'] ?? 0,
                    'currency_id' => $currency->id,
                    'currency_amount' => $amount,
                    'base_amount' => $amount * $exchangeRate,
                    'exchange_rate' => $exchangeRate,
                    'reference' => $data['reference'] ?? null,
                ]);
            }

            return $entry;
        });
    }

    protected function generateEntryNumber(Organization $organization, Journal $journal): string
    {
        $prefix = $journal->code;
        $year = now()->format('Y');
        $sequence = JournalEntry::where('organization_id', $organization->id)
            ->where('journal_id', $journal->id)
            ->whereYear('entry_date', $year)
            ->count() + 1;

        return "{$prefix}-{$year}-".str_pad($sequence, 6, '0', STR_PAD_LEFT);
    }

    public function rules(): array
    {
        return [
            'journal_id' => ['required', 'exists:journals,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'fiscal_year_id' => ['required', 'exists:fiscal_years,id'],
            'accounting_period_id' => ['required', 'exists:accounting_periods,id'],
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:100'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'exists:accounts,id'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.debit' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'gte:0'],
        ];
    }
}
