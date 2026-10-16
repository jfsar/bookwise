<?php

namespace App\Notifications;

class ApprovalRequestCreated extends OrganizationNotification
{
    public function __construct(int $organizationId, public string $approvalRequestId, public string $subject)
    {
        parent::__construct($organizationId);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'approval_request_id' => $this->approvalRequestId,
            'subject' => $this->subject,
            'message' => 'Approval requested for '.$this->subject.'.',
        ];
    }
}
