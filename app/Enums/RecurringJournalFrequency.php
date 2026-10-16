<?php

namespace App\Enums;

use Carbon\Carbon;

enum RecurringJournalFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::Yearly => 'Yearly',
        };
    }

    /**
     * Return the occurrence that follows the given date.
     *
     * Calendar frequencies are shifted from the definition anchor so a run
     * scheduled on the 31st does not drift once a short month clamps the day.
     */
    public function advance(Carbon $current, Carbon $anchor): Carbon
    {
        $next = match ($this) {
            self::Daily => $current->copy()->addDay(),
            self::Weekly => $current->copy()->addWeek(),
            self::Monthly => $this->shiftMonths($current, $anchor, 1),
            self::Quarterly => $this->shiftMonths($current, $anchor, 3),
            self::Yearly => $this->shiftMonths($current, $anchor, 12),
        };

        return $next->copy()->startOfDay();
    }

    private function shiftMonths(Carbon $current, Carbon $anchor, int $months): Carbon
    {
        $elapsed = ($current->year - $anchor->year) * 12 + ($current->month - $anchor->month);
        $target = $anchor->copy()->addMonthsNoOverflow($elapsed + $months);
        $target->day = min($anchor->day, $target->daysInMonth);

        return $target;
    }
}
