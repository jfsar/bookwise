<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Models\VendorBill;

class VendorBillPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('view-bills', $organization);
    }

    public function view(User $user, VendorBill $bill): bool
    {
        return $user->hasPermissionTo('view-bills', $bill->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('create-bills', $organization);
    }

    public function post(User $user, VendorBill $bill): bool
    {
        return $user->hasPermissionTo('approve-bills', $bill->organization);
    }

    public function void(User $user, VendorBill $bill): bool
    {
        return $user->hasPermissionTo('void-bills', $bill->organization);
    }
}
