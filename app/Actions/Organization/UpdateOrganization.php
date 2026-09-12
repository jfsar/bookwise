<?php

namespace App\Actions\Organization;

use App\Actions\Action;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogService;

class UpdateOrganization
{
    use Action;

    public function handle(Organization $organization, array $data, ?User $user = null): Organization
    {
        $oldValues = $organization->only(array_keys($data));

        $organization->update($data);

        app(AuditLogService::class)->record(
            $organization,
            $user?->id,
            'organization.updated',
            Organization::class,
            $organization->id,
            $oldValues,
            $organization->fresh()->only(array_keys($data))
        );

        return $organization->fresh();
    }

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
