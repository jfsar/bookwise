<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Organization;
use App\Models\User;

class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment, Organization $organization): bool
    {
        if ($attachment->organization_id !== $organization->id) {
            return false;
        }

        return true;
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization);
    }

    public function update(User $user, Attachment $attachment, Organization $organization): bool
    {
        if ($attachment->organization_id !== $organization->id) {
            return false;
        }

        return $attachment->uploaded_by === $user->id;
    }

    public function delete(User $user, Attachment $attachment, Organization $organization): bool
    {
        if ($attachment->organization_id !== $organization->id) {
            return false;
        }

        return $attachment->uploaded_by === $user->id || $user->hasPermissionTo('attachments.delete', $organization);
    }
}
