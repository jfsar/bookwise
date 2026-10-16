<?php

namespace App\Policies;

use App\Models\BankImportBatch;
use App\Models\Organization;
use App\Models\User;

class BankImportBatchPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('view-bank-transactions', $organization);
    }

    public function view(User $user, BankImportBatch $batch): bool
    {
        return $user->hasPermissionTo('view-bank-transactions', $batch->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('import-bank-transactions', $organization);
    }
}
