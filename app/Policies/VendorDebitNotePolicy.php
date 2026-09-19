<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Models\VendorDebitNote;

class VendorDebitNotePolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('view-vendor-debit-notes', $organization);
    }

    public function view(User $user, VendorDebitNote $debitNote): bool
    {
        return $user->hasPermissionTo('view-vendor-debit-notes', $debitNote->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('create-vendor-debit-notes', $organization);
    }

    public function post(User $user, VendorDebitNote $debitNote): bool
    {
        return $user->hasPermissionTo('approve-vendor-debit-notes', $debitNote->organization);
    }

    public function void(User $user, VendorDebitNote $debitNote): bool
    {
        return $user->hasPermissionTo('void-vendor-debit-notes', $debitNote->organization);
    }
}
