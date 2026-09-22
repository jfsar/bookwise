<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVendorRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'payment_terms_days' => ['required', 'integer', 'min:0'],
            'payable_account_id' => ['required', 'exists:accounts,id'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive', 'archived'])],
        ];
    }
}
