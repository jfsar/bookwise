<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MatchBankTransactionRequest extends FormRequest
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
            'bank_transaction_id' => ['required', 'exists:bank_transactions,id'],
            'journal_entry_line_id' => ['nullable', 'exists:journal_entry_lines,id'],
            'matched_amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
