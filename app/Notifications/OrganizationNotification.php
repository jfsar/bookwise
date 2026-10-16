<?php

namespace App\Notifications;

use App\Contracts\OrganizationNotification as OrganizationNotificationContract;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Base class for organization-scoped notifications. Every database notification
 * carries its organization_id so the notification center can enforce tenant
 * isolation (AP-003) instead of trusting the notifiable's current context.
 */
abstract class OrganizationNotification extends Notification implements OrganizationNotificationContract
{
    use Queueable;

    public function __construct(public int $organizationId) {}

    public function via(object $notifiable): array
    {
        return ['organization-database'];
    }

    public function organizationId(): int
    {
        return $this->organizationId;
    }
}
