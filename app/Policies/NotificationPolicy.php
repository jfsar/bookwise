<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;

class NotificationPolicy
{
    public function view(User $user, Notification $notification, Organization $organization): bool
    {
        if ($notification->organization_id !== $organization->id) {
            return false;
        }

        return $notification->notifiable_type === User::class && $notification->notifiable_id === $user->id;
    }

    public function update(User $user, Notification $notification, Organization $organization): bool
    {
        return $this->view($user, $notification, $organization);
    }

    public function delete(User $user, Notification $notification, Organization $organization): bool
    {
        return $this->view($user, $notification, $organization);
    }

    public function markAsRead(User $user, Notification $notification, Organization $organization): bool
    {
        return $this->view($user, $notification, $organization);
    }

    public function markAsUnread(User $user, Notification $notification, Organization $organization): bool
    {
        return $this->view($user, $notification, $organization);
    }
}
