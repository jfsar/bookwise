<?php

namespace App\Services;

use App\Enums\OutboxEventStatus;
use App\Events\JournalPosted;
use App\Models\JournalEntry;
use App\Models\OutboxEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Relays stored outbox rows to the event dispatcher (§0.25).
 *
 * Publication is idempotent: a row is only handled while pending, and the
 * transition to published happens in the same locked transaction that fires the
 * event. Failures are recorded with an attempt count and backoff so a poison
 * event cannot block the queue and a transient failure is retried.
 */
class OutboxPublisher
{
    public const MAX_ATTEMPTS = 5;

    /**
     * Publish a single outbox event. Returns true when the event was relayed.
     */
    public function publish(int $outboxEventId): bool
    {
        try {
            return DB::transaction(function () use ($outboxEventId): bool {
                $event = OutboxEvent::query()
                    ->whereKey($outboxEventId)
                    ->lockForUpdate()
                    ->first();

                if (! $event || $event->status !== OutboxEventStatus::Pending) {
                    return false;
                }

                event($this->resolve($event));

                $event->forceFill([
                    'status' => OutboxEventStatus::Published,
                    'published_at' => now(),
                    'attempt_count' => $event->attempt_count + 1,
                    'last_error' => null,
                ])->save();

                return true;
            });
        } catch (Throwable $e) {
            $this->recordFailure($outboxEventId, $e);

            return false;
        }
    }

    /**
     * Publish due pending events. Returns counts for reporting.
     *
     * @return array{published: int, failed: int}
     */
    public function publishPending(int $limit = 100): array
    {
        $ids = OutboxEvent::query()
            ->pending()
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $published = 0;
        $failed = 0;

        foreach ($ids as $id) {
            if ($this->publish($id)) {
                $published++;

                continue;
            }

            if (OutboxEvent::query()->whereKey($id)->value('status') === OutboxEventStatus::Failed->value) {
                $failed++;
            }
        }

        return ['published' => $published, 'failed' => $failed];
    }

    /**
     * Re-arm events that exhausted their attempts so they are eligible again.
     * Returns the number of events reset.
     */
    public function retryFailed(): int
    {
        return OutboxEvent::query()
            ->where('status', OutboxEventStatus::Failed)
            ->update([
                'status' => OutboxEventStatus::Pending->value,
                'attempt_count' => 0,
                'available_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Events that exhausted their attempts and are waiting for a manual retry.
     */
    public function failedCount(): int
    {
        return OutboxEvent::query()
            ->where('status', OutboxEventStatus::Failed)
            ->count();
    }

    /**
     * Rebuild the dispatchable event from its stored descriptor.
     */
    protected function resolve(OutboxEvent $event): object
    {
        return match ($event->event_type) {
            JournalPosted::class => new JournalPosted(
                JournalEntry::query()->findOrFail($event->aggregate_id)
            ),
            default => throw new RuntimeException("Unsupported outbox event type [{$event->event_type}]."),
        };
    }

    /**
     * Record a failed publication attempt and schedule a retry with backoff.
     */
    protected function recordFailure(int $outboxEventId, Throwable $e): void
    {
        DB::transaction(function () use ($outboxEventId, $e): void {
            $event = OutboxEvent::query()
                ->whereKey($outboxEventId)
                ->lockForUpdate()
                ->first();

            if (! $event) {
                return;
            }

            $attempts = $event->attempt_count + 1;
            $status = $attempts >= self::MAX_ATTEMPTS
                ? OutboxEventStatus::Failed
                : OutboxEventStatus::Pending;

            $event->forceFill([
                'attempt_count' => $attempts,
                'last_error' => mb_substr($e->getMessage(), 0, 2000),
                'status' => $status,
                'available_at' => now()->addSeconds(min(300, 5 * (2 ** ($attempts - 1)))),
            ])->save();

            // Structured operational signal for the §0.30 outbox_retry_total /
            // queue_failure_total metrics.
            Log::log(
                $status === OutboxEventStatus::Failed ? 'error' : 'warning',
                $status === OutboxEventStatus::Failed ? 'outbox.failed' : 'outbox.retry',
                [
                    'outbox_event_id' => $event->id,
                    'event_type' => $event->event_type,
                    'attempts' => $attempts,
                    'status' => $status->value,
                    'error' => mb_substr($e->getMessage(), 0, 500),
                ],
            );

            if ($status === OutboxEventStatus::Pending) {
                app(OperationalMetrics::class)->increment('outbox_retry_total', [
                    'event_type' => class_basename($event->event_type),
                    'organization_id' => $event->organization_id,
                ]);
            }
        });
    }
}
