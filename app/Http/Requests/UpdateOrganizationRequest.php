<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->user();

        if (! $user) {
            return false;
        }

        /** @var Organization|null $organization */
        $organization = $this->route('organization');

        if (! $organization instanceof Organization) {
            return false;
        }

        return $user->can('update', $organization);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'display_name' => ['nullable', 'string', 'max:200'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:200'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country_code' => ['required', 'string', 'size:2'],
            'base_currency_id' => ['required', 'exists:currencies,id'],
            'timezone' => ['required', 'string', 'max:100'],
            'fiscal_year_start_month' => ['required', 'integer', 'between:1,12'],
            'date_format' => ['nullable', 'string', 'max:30'],
            'decimal_places' => ['nullable', 'integer', 'between:0,9'],
        ];
    }
}
