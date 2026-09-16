<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('view-customers', $organization);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->hasPermissionTo('view-customers', $customer->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('create-customers', $organization);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->hasPermissionTo('update-customers', $customer->organization);
    }

    public function archive(User $user, Customer $customer): bool
    {
        return $user->hasPermissionTo('archive-customers', $customer->organization);
    }
}
