<?php

namespace App\Notifications;

class JobFailed extends OrganizationNotification
{
    public function __construct(int $organizationId, public string $jobName, public ?string $exceptionMessage = null)
    {
        parent::__construct($organizationId);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'job_name' => $this->jobName,
            'exception_message' => $this->exceptionMessage,
            'message' => 'Job '.$this->jobName.' failed.',
        ];
    }
}
