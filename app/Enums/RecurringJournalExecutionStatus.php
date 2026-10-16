<?php

namespace App\Enums;

enum RecurringJournalExecutionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
