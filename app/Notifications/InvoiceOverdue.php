<?php

namespace App\Notifications;

class InvoiceOverdue extends OrganizationNotification
{
    public function __construct(int $organizationId, public string $invoiceId, public string $invoiceNumber, public string $dueDate)
    {
        parent::__construct($organizationId);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'invoice_number' => $this->invoiceNumber,
            'due_date' => $this->dueDate,
            'message' => 'Invoice '.$this->invoiceNumber.' is overdue.',
        ];
    }
}
