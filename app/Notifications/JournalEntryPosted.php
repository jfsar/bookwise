<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class JournalEntryPosted extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $journalEntryId, public string $entryNumber) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'journal_entry_id' => $this->journalEntryId,
            'entry_number' => $this->entryNumber,
            'message' => 'Journal entry '.$this->entryNumber.' has been posted.',
        ];
    }
}
