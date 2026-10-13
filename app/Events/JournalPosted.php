<?php

namespace App\Events;

use App\Models\JournalEntry;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JournalPosted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public JournalEntry $entry) {}
}
