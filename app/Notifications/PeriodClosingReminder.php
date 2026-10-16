<?php

namespace App\Notifications;

class PeriodClosingReminder extends OrganizationNotification
{
    public function __construct(int $organizationId, public string $periodId, public string $periodName, public string $closeDate)
    {
        parent::__construct($organizationId);
    }

    /**
     * @return array<string, mixed>
     */
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
