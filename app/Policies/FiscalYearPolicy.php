<?php

namespace App\Policies;

use App\Models\FiscalYear;
use App\Models\Organization;
use App\Models\User;

class FiscalYearPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('view-fiscal-years', $organization);
    }

    public function view(User $user, FiscalYear $fiscalYear): bool
    {
        return $user->hasPermissionTo('view-fiscal-years', $fiscalYear->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('manage-fiscal-years', $organization);
    }

    public function update(User $user, FiscalYear $fiscalYear): bool
    {
        return $user->hasPermissionTo('manage-fiscal-years', $fiscalYear->organization);
    }
}
