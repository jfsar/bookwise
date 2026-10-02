<?php

namespace App\Console\Commands\Notifications;

use App\Actions\Notification\SendVendorBillDueReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:send-vendor-bill-due {--days=7 : Days ahead to treat a bill as due soon}')]
#[Description('Remind staff about vendor bills falling due')]
class SendVendorBillDueNotifications extends Command
{
    public function handle(SendVendorBillDueReminders $reminders): int
    {
        $sent = $reminders->handle(null, max(1, (int) $this->option('days')));

        $this->info("Sent {$sent} vendor bill reminder(s).");

        return Command::SUCCESS;
    }
}
