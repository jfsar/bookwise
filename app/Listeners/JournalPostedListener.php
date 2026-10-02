<?php

namespace App\Listeners;

use App\Enums\JournalEntryStatus;
use App\Events\JournalPosted;
use App\Models\JournalEntry;
use App\Notifications\JournalEntryPosted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class JournalPostedListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(JournalPosted $event): void
    {
        $organizationId = $event->organizationId;

        JournalEntry::with('creator')
            ->where('organization_id', $organizationId)
            ->where('status', JournalEntryStatus::Posted)
            ->whereNull('reversal_of_id')
            ->latest('posted_at')
            ->take(5)
            ->get()
            ->each(function ($entry) {
                if ($entry->creator) {
                    $entry->creator->notify(new JournalEntryPosted($entry->ulid ?? (string) $entry->id, $entry->entry_number));
                }
            });
    }
}
