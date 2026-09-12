<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('view-audit-logs', $organization);
    }
}
