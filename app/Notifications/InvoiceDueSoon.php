<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InvoiceDueSoon extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $invoiceId, public string $invoiceNumber, public string $dueDate) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'invoice_number' => $this->invoiceNumber,
            'due_date' => $this->dueDate,
            'message' => 'Invoice '.$this->invoiceNumber.' is due soon ('.$this->dueDate.').',
        ];
    }
}
