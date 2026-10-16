<?php

namespace App\Enums;

enum OutboxEventStatus: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Published => 'Published',
            self::Failed => 'Failed',
        };
    }
}
