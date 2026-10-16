<?php

namespace App\Events;

use App\Contracts\OutboxableEvent;
use App\Models\JournalEntry;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JournalPosted implements OutboxableEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public JournalEntry $entry) {}

    public function outboxEventType(): string
    {
        return self::class;
    }

    public function outboxAggregateType(): string
    {
        return 'journal_entry';
    }

    public function outboxAggregateId(): int
    {
        return (int) $this->entry->getKey();
    }

    public function outboxOrganizationId(): ?int
    {
        return $this->entry->organization_id;
    }

    public function outboxPayload(): array
    {
        return [
            'journal_entry_id' => (int) $this->entry->getKey(),
            'entry_number' => $this->entry->entry_number,
            'organization_id' => $this->entry->organization_id,
        ];
    }
}
