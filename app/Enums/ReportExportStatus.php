<?php

namespace App\Enums;

enum ReportExportStatus: string
{
    case Queued = 'queued';
    case Generating = 'generating';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Generating => 'Generating',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }
}
