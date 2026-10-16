<?php

namespace App\Listeners;

use App\Events\JournalPosted;
use App\Notifications\JournalEntryPosted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class JournalPostedListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Queue listeners are normally deferred until the transaction commits, but
     * the transactional outbox already guarantees delivery: dispatching inside
     * the transaction makes the push atomic with the outbox row's Published
     * transition, so a crash can never leave a Published row whose listener job
     * was never queued (§0.25).
     */
    public $afterCommit = false;

    public function handle(JournalPosted $event): void
    {
        $entry = $event->entry;

        if ($entry->reversal_of_id !== null) {
            return;
        }

        $creator = $entry->creator;

        if (! $creator) {
            return;
        }

        $journalEntryId = $entry->ulid ?? (string) $entry->getKey();

        // Queue delivery is at-least-once: a worker that dies after notifying but
        // before acknowledging replays this listener. Skip when the creator was
        // already notified for this entry so a replay cannot create a duplicate.
        $alreadyNotified = $creator->notifications()
            ->where('type', JournalEntryPosted::class)
            ->whereJsonContains('data', ['journal_entry_id' => $journalEntryId])
            ->exists();

        if ($alreadyNotified) {
            return;
        }

        $creator->notify(new JournalEntryPosted(
            $entry->organization_id,
            $journalEntryId,
            $entry->entry_number
        ));
    }
}
