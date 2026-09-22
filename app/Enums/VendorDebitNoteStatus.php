<?php

namespace App\Enums;

enum VendorDebitNoteStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Used = 'used';
    case Voided = 'voided';

    public function isPostable(): bool
    {
        return $this === self::Draft;
    }

    public function isVoidable(): bool
    {
        return $this === self::Draft;
    }
}
