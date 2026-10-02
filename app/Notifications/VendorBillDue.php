<?php

namespace App\Notifications;

class VendorBillDue extends OrganizationNotification
{
    public function __construct(int $organizationId, public string $billId, public string $billNumber, public string $dueDate)
    {
        parent::__construct($organizationId);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'bill_id' => $this->billId,
            'bill_number' => $this->billNumber,
            'due_date' => $this->dueDate,
            'message' => 'Vendor bill '.$this->billNumber.' is due on '.$this->dueDate.'.',
        ];
    }
}
