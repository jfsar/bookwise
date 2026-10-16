<?php

namespace Tests\Feature;

use App\Actions\JournalEntry\PostJournalEntry;
use App\Enums\JournalEntryStatus;
use App\Enums\OutboxEventStatus;
use App\Events\JournalPosted;
use App\Models\JournalEntry;
use App\Models\OutboxEvent;
use App\Models\User;
use App\Notifications\JournalEntryPosted;
use App\Services\OutboxPublisher;
use App\Services\OutboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OutboxPublicationTest extends TestCase
{
    use RefreshDatabase;

    private function pendingEventFor(JournalEntry $entry, string $eventType = JournalPosted::class): OutboxEvent
    {
        return OutboxEvent::create([
            'organization_id' => $entry->organization_id,
            'aggregate_type' => 'journal_entry',
            'aggregate_id' => $entry->id,
            'event_type' => $eventType,
            'payload' => ['journal_entry_id' => $entry->id],
            'status' => OutboxEventStatus::Pending,
            'available_at' => now(),
        ]);
    }

    public function test_recording_persists_the_event_descriptor(): void
    {
        $entry = JournalEntry::factory()->posted()->create();

        app(OutboxService::class)->record(new JournalPosted($entry));

        $event = OutboxEvent::sole();

        $this->assertSame($entry->organization_id, $event->organization_id);
        $this->assertSame('journal_entry', $event->aggregate_type);
        $this->assertSame($entry->id, $event->aggregate_id);
        $this->assertSame(JournalPosted::class, $event->event_type);
        $this->assertSame($entry->id, $event->payload['journal_entry_id']);
        $this->assertSame($entry->entry_number, $event->payload['entry_number']);
    }

    public function test_posting_an_entry_records_and_publishes_an_outbox_event(): void
    {
        $entry = JournalEntry::factory()->create(['status' => JournalEntryStatus::Approved]);
        $poster = User::factory()->create();

        (new PostJournalEntry)->handle($entry, $poster);

        $event = OutboxEvent::sole();

        $this->assertSame($entry->id, $event->aggregate_id);
        $this->assertSame(JournalPosted::class, $event->event_type);
        $this->assertSame(OutboxEventStatus::Published, $event->status);
        $this->assertNotNull($event->published_at);
    }

    public function test_publishing_dispatches_the_event_to_consumers(): void
    {
        $entry = JournalEntry::factory()->posted()->create();
        $event = $this->pendingEventFor($entry);

        $this->assertTrue(app(OutboxPublisher::class)->publish($event->id));

        $event->refresh();
        $this->assertSame(OutboxEventStatus::Published, $event->status);
        $this->assertSame(1, $event->attempt_count);
        $this->assertSame(1, $entry->creator->notifications()->where('type', JournalEntryPosted::class)->count());
    }

    public function test_publishing_is_idempotent_and_does_not_replay_consumers(): void
    {
        $entry = JournalEntry::factory()->posted()->create();
        $event = $this->pendingEventFor($entry);
        $publisher = app(OutboxPublisher::class);

        $this->assertTrue($publisher->publish($event->id));
        $this->assertFalse($publisher->publish($event->id));

        $this->assertSame(1, $entry->creator->notifications()->where('type', JournalEntryPosted::class)->count());
    }

    public function test_unknown_event_types_are_marked_failed_after_max_attempts(): void
    {
        $entry = JournalEntry::factory()->posted()->create();
        $event = $this->pendingEventFor($entry, 'App\\Events\\DoesNotExist');
        $publisher = app(OutboxPublisher::class);

        for ($attempt = 0; $attempt < OutboxPublisher::MAX_ATTEMPTS; $attempt++) {
            $this->assertFalse($publisher->publish($event->id));
        }

        $event->refresh();
        $this->assertSame(OutboxEventStatus::Failed, $event->status);
        $this->assertSame(OutboxPublisher::MAX_ATTEMPTS, $event->attempt_count);
        $this->assertNotNull($event->last_error);
    }

    public function test_command_publishes_due_pending_events(): void
    {
        $entry = JournalEntry::factory()->posted()->create();
        $event = $this->pendingEventFor($entry);

        $this->artisan('outbox:publish')->assertSuccessful();

        $event->refresh();
        $this->assertSame(OutboxEventStatus::Published, $event->status);
    }

    public function test_command_ignores_events_that_are_not_yet_due(): void
    {
        $entry = JournalEntry::factory()->posted()->create();
        $event = $this->pendingEventFor($entry);
        $event->forceFill(['available_at' => now()->addHour()])->save();

        $this->artisan('outbox:publish')->assertSuccessful();

        $event->refresh();
        $this->assertSame(OutboxEventStatus::Pending, $event->status);
        $this->assertSame(0, $entry->creator->notifications()->where('type', JournalEntryPosted::class)->count());
    }

    public function test_listener_job_is_pushed_before_the_publication_transaction_commits(): void
    {
        $entry = JournalEntry::factory()->posted()->create();
        $event = $this->pendingEventFor($entry);

        // The production connection defers queued dispatches to the commit, which
        // leaves a window where the outbox row is Published but the listener job
        // was never pushed. The listener opts out of that deferral so the push is
        // part of the same transaction as the Published transition.
        config(['queue.default' => 'database']);

        DB::beginTransaction();

        $this->assertTrue(app(OutboxPublisher::class)->publish($event->id));

        $jobsDuringTransaction = DB::table('jobs')->count();

        DB::rollBack();

        $this->assertGreaterThan(
            0,
            $jobsDuringTransaction,
            'The queued listener must be pushed inside the publication transaction, before commit.'
        );
    }

    public function test_retry_failed_command_rearms_and_publishes_exhausted_events(): void
    {
        $entry = JournalEntry::factory()->posted()->create();
        $event = $this->pendingEventFor($entry);

        $event->forceFill([
            'status' => OutboxEventStatus::Failed,
            'attempt_count' => OutboxPublisher::MAX_ATTEMPTS,
            'last_error' => 'simulated failure',
            'available_at' => now()->subMinute(),
        ])->save();

        $this->artisan('outbox:retry-failed')->assertSuccessful();

        $event->refresh();
        $this->assertSame(OutboxEventStatus::Published, $event->status);
        $this->assertSame(1, $event->attempt_count);
        $this->assertNull($event->last_error);
        $this->assertNotNull($event->published_at);
        $this->assertSame(1, $entry->creator->notifications()->where('type', JournalEntryPosted::class)->count());
    }

    public function test_publish_command_surfaces_events_awaiting_retry(): void
    {
        $entry = JournalEntry::factory()->posted()->create();
        $event = $this->pendingEventFor($entry);

        $event->forceFill([
            'status' => OutboxEventStatus::Failed,
            'attempt_count' => OutboxPublisher::MAX_ATTEMPTS,
            'last_error' => 'boom',
        ])->save();

        $this->artisan('outbox:publish')
            ->expectsOutputToContain('1 exhausted event(s) awaiting manual retry')
            ->assertSuccessful();

        $event->refresh();
        $this->assertSame(OutboxEventStatus::Failed, $event->status);
        $this->assertSame(OutboxPublisher::MAX_ATTEMPTS, $event->attempt_count);
    }
}
