<?php

namespace App\Actions\Organization;

use App\Actions\Action;
use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateOrganization
{
    use Action;

    /**
     * Create a new organization and assign the creator as an active member.
     */
    public function handle(array $data, User $creator): Organization
    {
        return DB::transaction(function () use ($data, $creator) {
            $organization = Organization::create([
                'name' => $data['name'],
                'display_name' => $data['display_name'] ?? $data['name'],
                'code' => $data['code'],
                'registration_number' => $data['registration_number'] ?? null,
                'tax_number' => $data['tax_number'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'country_code' => $data['country_code'],
                'base_currency_id' => $data['base_currency_id'],
                'timezone' => $data['timezone'] ?? 'UTC',
                'fiscal_year_start_month' => $data['fiscal_year_start_month'] ?? 1,
                'date_format' => $data['date_format'] ?? 'Y-m-d',
                'decimal_places' => $data['decimal_places'] ?? 2,
                'created_by' => $creator->id,
            ]);

            $organization->users()->attach($creator->id, [
                'membership_status' => MembershipStatus::Active,
                'joined_at' => now(),
            ]);

            return $organization;
        });
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'display_name' => ['nullable', 'string', 'max:200'],
            'code' => ['required', 'string', 'max:50', 'unique:organizations,code'],
            'country_code' => ['required', 'string', 'size:2'],
            'base_currency_id' => ['required', 'exists:currencies,id'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'fiscal_year_start_month' => ['nullable', 'integer', 'between:1,12'],
            'date_format' => ['nullable', 'string', 'max:30'],
            'decimal_places' => ['nullable', 'integer', 'between:0,9'],
        ];
    }
}
