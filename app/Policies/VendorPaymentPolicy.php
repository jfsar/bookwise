<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Models\VendorPayment;

class VendorPaymentPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('view-vendor-payments', $organization);
    }

    public function view(User $user, VendorPayment $payment): bool
    {
        return $user->hasPermissionTo('view-vendor-payments', $payment->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('create-vendor-payments', $organization);
    }

    public function post(User $user, VendorPayment $payment): bool
    {
        return $user->hasPermissionTo('approve-vendor-payments', $payment->organization);
    }

    public function void(User $user, VendorPayment $payment): bool
    {
        return $user->hasPermissionTo('void-vendor-payments', $payment->organization);
    }
}
