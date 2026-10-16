<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBankTransactionRequest extends FormRequest
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
            'transaction_date' => ['required', 'date'],
            'value_date' => ['nullable', 'date'],
            'direction' => ['required', Rule::in(['debit', 'credit'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
