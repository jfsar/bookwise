<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\Organization;
use App\Models\User;

class AccountPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('view-accounts', $organization);
    }

    public function view(User $user, Account $account): bool
    {
        return $user->hasPermissionTo('view-accounts', $account->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('create-accounts', $organization);
    }

    public function update(User $user, Account $account): bool
    {
        return $user->hasPermissionTo('update-accounts', $account->organization);
    }

    public function archive(User $user, Account $account): bool
    {
        return $user->hasPermissionTo('archive-accounts', $account->organization);
    }
}
