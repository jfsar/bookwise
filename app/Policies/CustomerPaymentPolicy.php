<?php

namespace App\Policies;

use App\Models\CustomerPayment;
use App\Models\Organization;
use App\Models\User;

class CustomerPaymentPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('view-payments', $organization);
    }

    public function view(User $user, CustomerPayment $payment): bool
    {
        return $user->hasPermissionTo('view-payments', $payment->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('create-payments', $organization);
    }

    public function post(User $user, CustomerPayment $payment): bool
    {
        return $user->hasPermissionTo('approve-payments', $payment->organization);
    }
}
