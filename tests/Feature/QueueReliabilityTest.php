<?php

namespace Tests\Feature;

use App\Enums\OutboxEventStatus;
use App\Jobs\ExportReportJob;
use App\Models\JournalEntry;
use App\Models\OutboxEvent;
use App\Services\OutboxPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Tests\TestCase;

class QueueReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_jobs_are_not_dispatched_until_the_surrounding_transaction_commits(): void
    {
        $this->assertTrue(config('queue.connections.database.after_commit'));
        $this->assertTrue(config('queue.connections.redis.after_commit'));
    }

    public function test_failed_and_batched_jobs_use_postgres_not_sqlite(): void
    {
        $this->assertSame('pgsql_testing', config('queue.failed.database'));
        $this->assertSame('pgsql_testing', config('queue.batching.database'));
    }

    public function test_the_export_job_encodes_a_bounded_retry_policy(): void
    {
        $job = new ExportReportJob(1);

        $this->assertSame(3, $job->tries);
        $this->assertSame([30, 120], $job->backoff);
    }

    public function test_the_dead_letter_store_is_pruned_on_a_schedule(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('queue:prune-failed')
            ->assertSuccessful();
    }

    public function test_outbox_retries_emit_a_structured_log(): void
    {
        Log::spy();
        $event = $this->eventWithUnsupportedType();

        app(OutboxPublisher::class)->publish($event->id);

        Log::shouldHaveReceived('log')
            ->with('warning', 'outbox.retry', Mockery::on(
                fn (array $context): bool => $context['outbox_event_id'] === $event->id
                    && $context['attempts'] === 1
                    && $context['status'] === OutboxEventStatus::Pending->value
            ))
            ->once();
    }

    public function test_outbox_retries_emit_a_metric(): void
    {
        $handler = new TestHandler;
        Log::getLogger()->pushHandler($handler);

        $event = $this->eventWithUnsupportedType();

        app(OutboxPublisher::class)->publish($event->id);

        $metric = collect($handler->getRecords())
            ->first(fn (LogRecord $record): bool => ($record->context['metric'] ?? null) === 'outbox_retry_total');

        $this->assertNotNull($metric);
        $this->assertSame(1, $metric->context['value']);
        $this->assertSame('UnsupportedEvent', $metric->context['tags']['event_type']);
    }

    public function test_outbox_final_failure_emits_a_structured_log(): void
    {
        Log::spy();
        $event = $this->eventWithUnsupportedType();
        $publisher = app(OutboxPublisher::class);

        for ($attempt = 0; $attempt < OutboxPublisher::MAX_ATTEMPTS; $attempt++) {
            $publisher->publish($event->id);
        }

        Log::shouldHaveReceived('log')
            ->with('error', 'outbox.failed', Mockery::on(
                fn (array $context): bool => $context['status'] === OutboxEventStatus::Failed->value
                    && $context['attempts'] === OutboxPublisher::MAX_ATTEMPTS
            ))
            ->once();
    }

    private function eventWithUnsupportedType(): OutboxEvent
    {
        $entry = JournalEntry::factory()->posted()->create();

        return OutboxEvent::create([
            'organization_id' => $entry->organization_id,
            'aggregate_type' => 'journal_entry',
            'aggregate_id' => $entry->id,
            'event_type' => 'App\\Events\\UnsupportedEvent',
            'payload' => [],
            'status' => OutboxEventStatus::Pending,
            'available_at' => now(),
        ]);
    }
}
