<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ApprovalDecisionMade extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $approvalRequestId, public string $decision, public string $subject) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'approval_request_id' => $this->approvalRequestId,
            'decision' => $this->decision,
            'subject' => $this->subject,
            'message' => 'Approval request '.$this->decision.': '.$this->subject,
        ];
    }
}
