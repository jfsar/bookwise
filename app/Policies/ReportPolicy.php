<?php

namespace App\Policies;

use App\Actions\Organization\SwitchOrganization;
use App\Models\ReportExport;
use App\Models\User;

class ReportPolicy
{
    /**
     * Whether the user may view the reporting area, scoped to their current
     * active organization.
     */
    public function viewReports(User $user): bool
    {
        $organization = SwitchOrganization::current($user);

        return $organization !== null && $user->hasPermissionTo('view-reports', $organization);
    }

    public function download(User $user, ReportExport $reportExport): bool
    {
        return $user->hasPermissionTo('view-reports', $reportExport->organization);
    }
}
