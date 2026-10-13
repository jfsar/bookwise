<?php

namespace App\Listeners;

use App\Events\JournalPosted;
use App\Notifications\JournalEntryPosted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class JournalPostedListener implements ShouldQueue
{
    use InteractsWithQueue;

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

        $creator->notify(new JournalEntryPosted(
            $entry->organization_id,
            $entry->ulid ?? (string) $entry->getKey(),
            $entry->entry_number
        ));
    }
}
