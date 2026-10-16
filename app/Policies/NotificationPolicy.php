<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;

/**
 * Notifications are personal to the recipient, so ownership of the row is the
 * authorization boundary rather than a permission slug. The organization must
 * also match the active tenant so a stale or forged id cannot read another
 * organization's notification (AP-003).
 */
class NotificationPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization);
    }

    public function view(User $user, Notification $notification, Organization $organization): bool
    {
        return $this->owns($user, $notification, $organization);
    }

    public function update(User $user, Notification $notification, Organization $organization): bool
    {
        return $this->owns($user, $notification, $organization);
    }

    /**
     * Marking every unread notification read is only ever a mutation of the
     * user's own rows within their active organization.
     */
    public function updateAny(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization);
    }

    public function delete(User $user, Notification $notification, Organization $organization): bool
    {
        return $this->owns($user, $notification, $organization);
    }

    private function owns(User $user, Notification $notification, Organization $organization): bool
    {
        if ($notification->organization_id !== $organization->id) {
            return false;
        }

        return $notification->notifiable_type === $user->getMorphClass()
            && $notification->notifiable_id === $user->getKey();
    }
}
