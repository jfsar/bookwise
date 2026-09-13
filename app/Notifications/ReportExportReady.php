<?php

namespace App\Notifications;

use App\Models\ReportExport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportExportReady extends Notification
{
    use Queueable;

    public function __construct(public ReportExport $reportExport) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your report export is ready')
            ->greeting('Hello!')
            ->line('Your '.$this->reportExport->report_type->label().' export is ready to download.')
            ->line('Report reference: '.$this->reportExport->file_name);
    }
}
