<?php

namespace App\Enums;

enum RecurringJournalStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
}
