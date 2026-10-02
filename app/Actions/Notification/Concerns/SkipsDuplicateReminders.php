<?php

namespace App\Actions\Notification\Concerns;

use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;

/**
 * Reminder commands run on a schedule and may be retried after a partial
 * failure, so each reminder is idempotent per recipient, per subject, per run
 * day (AP-008). Notifications are never financial state, so skipping a
 * duplicate is always safe.
 */
trait SkipsDuplicateReminders
{
    /**
     * Whether this recipient already received the same reminder for the same
     * subject earlier today. Notifications.data is a text column, so the JSON
     * lookup has to cast to jsonb on PostgreSQL.
     */
    protected function alreadyRemindedToday(User $user, Organization $organization, string $notificationType, string $subjectKey, string $subjectValue): bool
    {
        return Notification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->where('organization_id', $organization->id)
            ->where('type', $notificationType)
            ->whereRaw('(data::jsonb ->> ?) = ?', [$subjectKey, $subjectValue])
            ->where('created_at', '>=', now()->startOfDay())
            ->exists();
    }
}
