<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankReconciliationRequest extends FormRequest
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
            'bank_account_id' => ['required', 'exists:bank_accounts,id'],
            'statement_date' => ['required', 'date'],
            'statement_start_date' => ['required', 'date'],
            'statement_end_date' => ['required', 'date', 'after_or_equal:statement_start_date'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'closing_balance' => ['required', 'numeric', 'min:0'],
        ];
    }
}
