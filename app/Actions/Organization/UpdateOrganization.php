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
        $data = array_replace(
            ['date_format' => 'Y-m-d', 'decimal_places' => 2],
            array_filter($data, fn ($value) => $value !== null),
        );

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
}
