<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class JobFailed extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $jobName, public ?string $exceptionMessage = null) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'job_name' => $this->jobName,
            'exception_message' => $this->exceptionMessage,
            'message' => 'Job '.$this->jobName.' failed.',
        ];
    }
}
