<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VendorBillDue extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $billId, public string $billNumber, public string $dueDate) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

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
