<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVendorDebitNoteRequest extends FormRequest
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
            'vendor_id' => ['required', 'exists:vendors,id'],
            'bill_id' => ['required', 'exists:vendor_bills,id'],
            'date' => ['required', 'date'],
            'subtotal' => ['required', 'numeric', 'gte:0'],
            'tax_amount' => ['required', 'numeric', 'gte:0'],
            'reason' => ['required', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
