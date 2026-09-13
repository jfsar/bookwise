<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('manage-users', $organization);
    }

    public function update(User $user, Role $role, Organization $organization): bool
    {
        return $user->hasPermissionTo('manage-users', $organization)
            && $role->organization_id === $organization->id;
    }

    public function delete(User $user, Role $role, Organization $organization): bool
    {
        return $user->hasPermissionTo('manage-users', $organization)
            && $role->organization_id === $organization->id;
    }
}
