<?php

namespace App\Contracts;

interface OrganizationNotification
{
    /**
     * The organization that owns this notification. Stored on the notification
     * row so the notification center can enforce tenant scoping.
     */
    public function organizationId(): int;
}
