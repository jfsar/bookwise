<?php

namespace App\Services;

use App\Contracts\OutboxableEvent;
use App\Enums\OutboxEventStatus;
use App\Jobs\PublishOutboxEventJob;
use App\Models\OutboxEvent;
use Illuminate\Support\Facades\DB;

/**
 * Persists a financial lifecycle event to the transactional outbox (§0.25).
 *
 * The row is written inside the caller's open transaction, so it commits with
 * the financial fact or not at all. A relay job is registered after commit to
 * attempt publication immediately; if the process dies before publication, the
 * scheduled outbox worker picks the row up. Consumers must still be idempotent
 * because delivery is at-least-once.
 */
class OutboxService
{
    public function record(OutboxableEvent $event): OutboxEvent
    {
        $record = OutboxEvent::create([
            'organization_id' => $event->outboxOrganizationId(),
            'aggregate_type' => $event->outboxAggregateType(),
            'aggregate_id' => $event->outboxAggregateId(),
            'event_type' => $event->outboxEventType(),
            'payload' => $event->outboxPayload(),
            'status' => OutboxEventStatus::Pending,
            'available_at' => now(),
        ]);

        DB::afterCommit(fn () => PublishOutboxEventJob::dispatch($record->id));

        return $record;
    }
}
