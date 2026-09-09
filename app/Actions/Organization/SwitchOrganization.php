<?php

namespace App\Actions\Organization;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Session;

class SwitchOrganization
{
    /**
     * Switch the user's active organization context.
     */
    public function handle(User $user, Organization $organization): void
    {
        if (! $user->belongsToOrganization($organization)) {
            abort(403, 'You do not belong to this organization.');
        }

        Session::put('current_organization_id', $organization->id);
    }

    /**
     * Get the user's current organization from session.
     */
    public static function current(?User $user): ?Organization
    {
        if (! $user) {
            return null;
        }

        $orgId = Session::get('current_organization_id');

        if ($orgId) {
            $organization = $user->organizations()
                ->where('organizations.id', $orgId)
                ->wherePivot('membership_status', 'active')
                ->first();

            if ($organization) {
                return $organization;
            }
        }

        $organization = $user->organizations()
            ->wherePivot('membership_status', 'active')
            ->first();

        if ($organization) {
            Session::put('current_organization_id', $organization->id);
        }

        return $organization;
    }
}
