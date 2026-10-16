<?php

namespace App\Notifications;

class JournalEntryPosted extends OrganizationNotification
{
    public function __construct(int $organizationId, public string $journalEntryId, public string $entryNumber)
    {
        parent::__construct($organizationId);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'journal_entry_id' => $this->journalEntryId,
            'entry_number' => $this->entryNumber,
            'message' => 'Journal entry '.$this->entryNumber.' has been posted.',
        ];
    }
}
