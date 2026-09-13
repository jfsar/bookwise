<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization);
    }

    public function view(User $user, User $target): bool
    {
        return $user->id === $target->id;
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('manage-users', $organization);
    }

    public function update(User $user, User $target, Organization $organization): bool
    {
        return $user->hasPermissionTo('manage-users', $organization);
    }
}
