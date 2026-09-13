<?php

namespace App\Http\Requests;

use App\Models\Currency;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'country_code' => ['required', 'string', 'size:2', 'uppercase'],
            'base_currency_id' => ['required', 'integer', Rule::exists(Currency::class, 'id')->where('is_active', true)],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'fiscal_year_start_month' => ['required', 'integer', 'between:1,12'],
            'date_format' => ['nullable', 'string', 'max:20'],
            'decimal_places' => ['nullable', 'integer', 'between:0,9'],
        ];
    }
}
