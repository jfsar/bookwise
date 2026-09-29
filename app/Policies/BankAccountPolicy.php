<?php

namespace App\Policies;

use App\Models\BankAccount;
use App\Models\Organization;
use App\Models\User;

class BankAccountPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('view-bank-accounts', $organization);
    }

    public function view(User $user, BankAccount $bankAccount): bool
    {
        return $user->hasPermissionTo('view-bank-accounts', $bankAccount->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('create-bank-accounts', $organization);
    }

    public function update(User $user, BankAccount $bankAccount): bool
    {
        return $user->hasPermissionTo('update-bank-accounts', $bankAccount->organization);
    }

    public function archive(User $user, BankAccount $bankAccount): bool
    {
        return $user->hasPermissionTo('archive-bank-accounts', $bankAccount->organization);
    }
}
