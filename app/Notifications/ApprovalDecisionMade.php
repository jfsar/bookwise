<?php

namespace App\Notifications;

class ApprovalDecisionMade extends OrganizationNotification
{
    public function __construct(int $organizationId, public string $approvalRequestId, public string $decision, public string $subject)
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
            'decision' => $this->decision,
            'subject' => $this->subject,
            'message' => 'Approval request '.$this->decision.': '.$this->subject,
        ];
    }
}
