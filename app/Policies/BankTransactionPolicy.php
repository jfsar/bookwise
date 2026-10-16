<?php

namespace App\Policies;

use App\Models\BankTransaction;
use App\Models\Organization;
use App\Models\User;

class BankTransactionPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('view-bank-transactions', $organization);
    }

    public function view(User $user, BankTransaction $bankTransaction): bool
    {
        return $user->hasPermissionTo('view-bank-transactions', $bankTransaction->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('create-bank-transactions', $organization);
    }

    public function update(User $user, BankTransaction $bankTransaction): bool
    {
        return $user->hasPermissionTo('update-bank-transactions', $bankTransaction->organization);
    }
}
