<?php

namespace App\Enums;

enum ApprovalMode: string
{
    case Sequential = 'sequential';
    case Parallel = 'parallel';
}
