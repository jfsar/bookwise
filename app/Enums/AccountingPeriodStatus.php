<?php

namespace App\Enums;

enum AccountingPeriodStatus: string
{
    case Open = 'open';
    case Closing = 'closing';
    case Closed = 'closed';
}
