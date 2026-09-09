<?php

namespace App\Policies;

use App\Models\Journal;
use App\Models\Organization;
use App\Models\User;

class JournalPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('view-journals', $organization);
    }

    public function view(User $user, Journal $journal): bool
    {
        return $user->hasPermissionTo('view-journals', $journal->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('manage-journals', $organization);
    }

    public function update(User $user, Journal $journal): bool
    {
        return $user->hasPermissionTo('manage-journals', $journal->organization);
    }
}
