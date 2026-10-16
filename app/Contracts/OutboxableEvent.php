<?php

namespace App\Contracts;

/**
 * Financial lifecycle events implement this so they can be persisted to the
 * outbox inside the same transaction as the fact that produced them (§0.25).
 * The outbox worker rebuilds the event from these descriptors and re-publishes
 * it, guaranteeing at-least-once delivery that survives a crash between the
 * financial commit and publication.
 */
interface OutboxableEvent
{
    /**
     * Concrete event class name, used to resolve the publisher for a stored row.
     */
    public function outboxEventType(): string;

    /**
     * Domain aggregate kind the event describes (e.g. "journal_entry").
     */
    public function outboxAggregateType(): string;

    public function outboxAggregateId(): int;

    public function outboxOrganizationId(): ?int;

    /**
     * Minimal, stable snapshot the worker needs to rebuild and publish the event.
     *
     * @return array<string, mixed>
     */
    public function outboxPayload(): array;
}
