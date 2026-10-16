<?php

namespace App\Notifications\Channels;

use App\Contracts\OrganizationNotification;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * Persists organization_id alongside the standard database payload so that
 * notification rows stay tenant-scoped and NotificationPolicy can deny
 * cross-organization access. See .ai/rules/policies.md.
 */
class OrganizationDatabaseChannel extends DatabaseChannel
{
    /**
     * @param  mixed  $notifiable
     * @return array<string, mixed>
     */
    protected function buildPayload($notifiable, Notification $notification)
    {
        return array_merge(parent::buildPayload($notifiable, $notification), [
            'organization_id' => $notification instanceof OrganizationNotification
                ? $notification->organizationId()
                : null,
        ]);
    }
}
