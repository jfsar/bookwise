<?php

namespace App\Policies;

use App\Models\BankReconciliation;
use App\Models\Organization;
use App\Models\User;

class BankReconciliationPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('view-bank-reconciliations', $organization);
    }

    public function view(User $user, BankReconciliation $bankReconciliation): bool
    {
        return $user->hasPermissionTo('view-bank-reconciliations', $bankReconciliation->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('manage-bank-reconciliations', $organization);
    }

    public function update(User $user, BankReconciliation $bankReconciliation): bool
    {
        return $user->hasPermissionTo('manage-bank-reconciliations', $bankReconciliation->organization);
    }

    public function finalize(User $user, BankReconciliation $bankReconciliation): bool
    {
        return $user->hasPermissionTo('finalize-bank-reconciliations', $bankReconciliation->organization);
    }
}
