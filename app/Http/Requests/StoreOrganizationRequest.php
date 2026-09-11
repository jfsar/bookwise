<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationRequest extends FormRequest
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
            'display_name' => ['nullable', 'string', 'max:200'],
            'code' => ['required', 'string', 'max:50', 'unique:organizations,code'],
            'country_code' => ['required', 'string', 'size:2'],
            'base_currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'timezone' => ['required', 'string', 'max:100'],
            'fiscal_year_start_month' => ['required', 'integer', 'between:1,12'],
            'date_format' => ['nullable', 'string', 'max:20'],
            'decimal_places' => ['nullable', 'integer', 'between:0,9'],
        ];
    }
}
