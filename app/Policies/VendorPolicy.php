<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;

class VendorPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('view-vendors', $organization);
    }

    public function view(User $user, Vendor $vendor): bool
    {
        return $user->hasPermissionTo('view-vendors', $vendor->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('create-vendors', $organization);
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $user->hasPermissionTo('update-vendors', $vendor->organization);
    }

    public function archive(User $user, Vendor $vendor): bool
    {
        return $user->hasPermissionTo('archive-vendors', $vendor->organization);
    }
}
