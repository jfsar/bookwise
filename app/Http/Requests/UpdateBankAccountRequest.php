<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBankAccountRequest extends FormRequest
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
            'bank_name' => ['required', 'string', 'max:200'],
            'account_name' => ['required', 'string', 'max:200'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:100'],
            'routing_number' => ['nullable', 'string', 'max:100'],
            'account_id' => ['required', 'exists:accounts,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive', 'closed'])],
        ];
    }
}
