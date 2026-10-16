<?php

namespace App\Console\Commands\Notifications;

use App\Actions\Notification\SendInvoiceDueReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:send-invoice-due {--days=7 : Days ahead to treat an invoice as due soon}')]
#[Description('Remind staff about overdue invoices and invoices falling due')]
class SendInvoiceDueNotifications extends Command
{
    public function handle(SendInvoiceDueReminders $reminders): int
    {
        $sent = $reminders->handle(null, max(1, (int) $this->option('days')));

        $this->info("Sent {$sent} invoice reminder(s).");

        return Command::SUCCESS;
    }
}
