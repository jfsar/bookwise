<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Organization;
use App\Models\User;

class AttachmentPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('view-attachments', $organization);
    }

    public function view(User $user, Attachment $attachment): bool
    {
        return $this->belongsToOrganization($user, $attachment)
            && $user->hasPermissionTo('view-attachments', $attachment->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('create-attachments', $organization);
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $this->belongsToOrganization($user, $attachment)
            && $user->hasPermissionTo('delete-attachments', $attachment->organization);
    }

    /**
     * Cross-tenant access is denied regardless of permissions (SRS §0.27,
     * acceptance criterion 49).
     */
    private function belongsToOrganization(User $user, Attachment $attachment): bool
    {
        return $user->belongsToOrganization($attachment->organization);
    }
}
