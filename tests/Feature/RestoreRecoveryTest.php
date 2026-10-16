<?php

namespace Tests\Feature;

use App\Enums\OutboxEventStatus;
use App\Events\JournalPosted;
use App\Models\JournalEntry;
use App\Models\OutboxEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recovery/reprocessing behavior after a restore (§0.37): a database restored
 * from backup can leave durable work half-done — pending outbox rows that were
 * captured mid-flight. The crash-recovery relay must drain that backlog so no
 * event is lost.
 */
class RestoreRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_restored_backlog_of_pending_outbox_events_is_reprocessed(): void
    {
        $entry = JournalEntry::factory()->posted()->create();

        $event = OutboxEvent::create([
            'organization_id' => $entry->organization_id,
            'aggregate_type' => 'journal_entry',
            'aggregate_id' => $entry->id,
            'event_type' => JournalPosted::class,
            'payload' => ['journal_entry_id' => $entry->id],
            'status' => OutboxEventStatus::Pending,
            'available_at' => now()->subMinute(),
            'attempt_count' => 2,
        ]);

        $this->artisan('outbox:publish')->assertSuccessful();

        $this->assertSame(OutboxEventStatus::Published, $event->refresh()->status);
        $this->assertNotNull($event->published_at);
    }

    public function test_publishing_the_restored_backlog_is_safe_to_repeat(): void
    {
        $entry = JournalEntry::factory()->posted()->create();

        OutboxEvent::create([
            'organization_id' => $entry->organization_id,
            'aggregate_type' => 'journal_entry',
            'aggregate_id' => $entry->id,
            'event_type' => JournalPosted::class,
            'payload' => ['journal_entry_id' => $entry->id],
            'status' => OutboxEventStatus::Pending,
            'available_at' => now()->subMinute(),
        ]);

        $this->artisan('outbox:publish')->assertSuccessful();
        $this->artisan('outbox:publish')->assertSuccessful();

        $this->assertSame(OutboxEventStatus::Published, OutboxEvent::sole()->status);
    }
}
