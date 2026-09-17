<?php

namespace App\Policies;

use App\Models\CustomerCreditNote;
use App\Models\Organization;
use App\Models\User;

class CustomerCreditNotePolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('view-credit-notes', $organization);
    }

    public function view(User $user, CustomerCreditNote $creditNote): bool
    {
        return $user->hasPermissionTo('view-credit-notes', $creditNote->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('create-credit-notes', $organization);
    }

    public function post(User $user, CustomerCreditNote $creditNote): bool
    {
        return $user->hasPermissionTo('approve-credit-notes', $creditNote->organization);
    }

    public function void(User $user, CustomerCreditNote $creditNote): bool
    {
        return $user->hasPermissionTo('void-credit-notes', $creditNote->organization);
    }
}
