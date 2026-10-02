<?php

namespace App\Console\Commands\Notifications;

use App\Actions\Notification\SendPeriodClosingReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:send-period-closing-reminders {--days=7 : Days ahead of period end to send the reminder}')]
#[Description('Remind staff about accounting periods that are about to end')]
class SendPeriodClosingReminderNotifications extends Command
{
    public function handle(SendPeriodClosingReminders $reminders): int
    {
        $sent = $reminders->handle(null, max(1, (int) $this->option('days')));

        $this->info("Sent {$sent} period closing reminder(s).");

        return Command::SUCCESS;
    }
}
