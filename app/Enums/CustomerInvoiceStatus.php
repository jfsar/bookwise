<?php

namespace App\Enums;

enum CustomerInvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Posted = 'posted';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
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
