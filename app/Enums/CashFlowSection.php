<?php

namespace App\Enums;

enum CashFlowSection: string
{
    case Operating = 'operating';
    case Investing = 'investing';
    case Financing = 'financing';

    public function label(): string
    {
        return match ($this) {
            self::Operating => 'Operating',
            self::Investing => 'Investing',
            self::Financing => 'Financing',
        };
    }
}
