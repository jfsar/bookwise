<?php

namespace App\Jobs;

use App\Contracts\OrganizationScopedJob;
use App\Models\OutboxEvent;
use App\Services\OutboxPublisher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * After-commit relay for a single outbox row (§0.25). The scheduled
 * `outbox:publish` command is the crash-recovery counterpart.
 */
class PublishOutboxEventJob implements OrganizationScopedJob, ShouldQueue
{
    use Queueable;

    public function __construct(public int $outboxEventId) {}

    public function organizationId(): ?int
    {
        return OutboxEvent::find($this->outboxEventId)?->organization_id;
    }

    public function handle(OutboxPublisher $publisher): void
    {
        $publisher->publish($this->outboxEventId);
    }
}
