<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
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
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'exists:accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'gte:0'],
        ];
    }
}
