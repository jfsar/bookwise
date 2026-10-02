<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PeriodClosingReminder extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $periodId, public string $periodName, public string $closeDate) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'period_id' => $this->periodId,
            'period_name' => $this->periodName,
            'close_date' => $this->closeDate,
            'message' => 'Accounting period '.$this->periodName.' closes on '.$this->closeDate.'.',
        ];
    }
}
