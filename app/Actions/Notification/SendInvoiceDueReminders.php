<?php

namespace App\Actions\Notification;

use App\Actions\Action;
use App\Actions\Notification\Concerns\SkipsDuplicateReminders;
use App\Enums\CustomerInvoiceStatus;
use App\Models\CustomerInvoice;
use App\Models\User;
use App\Notifications\InvoiceDueSoon;
use App\Notifications\InvoiceOverdue;
use Illuminate\Support\Carbon;

/**
 * Reminds staff who can see invoices about posted/partially paid invoices that
 * are overdue or falling due. Notifications are advisory only — they never
 * affect invoice state.
 */
class SendInvoiceDueReminders
{
    use Action;
    use SkipsDuplicateReminders;

    public function handle(?Carbon $asOf = null, int $leadDays = 7): int
    {
        $today = ($asOf ?? Carbon::now())->copy()->startOfDay();
        $dueSoonBy = $today->copy()->addDays($leadDays);

        $invoices = CustomerInvoice::query()
            ->whereNotNull('due_date')
            ->whereIn('status', [CustomerInvoiceStatus::Posted, CustomerInvoiceStatus::PartiallyPaid])
            ->whereDate('due_date', '<=', $dueSoonBy->toDateString())
            ->orderBy('id')
            ->get();

        $sent = 0;

        foreach ($invoices as $invoice) {
            $organization = $invoice->organization;

            if (! $organization) {
                continue;
            }

            $recipients = User::membersWithPermission($organization, 'view-invoices');
            $overdue = $invoice->due_date->startOfDay()->lt($today);

            foreach ($recipients as $user) {
                $type = $overdue ? InvoiceOverdue::class : InvoiceDueSoon::class;
                $subjectKey = 'invoice_id';
                $subjectValue = $invoice->ulid ?? (string) $invoice->getKey();

                if ($this->alreadyRemindedToday($user, $organization, $type, $subjectKey, $subjectValue)) {
                    continue;
                }

                $user->notify($overdue
                    ? new InvoiceOverdue($organization->id, $subjectValue, $invoice->invoice_number, $invoice->due_date->toDateString())
                    : new InvoiceDueSoon($organization->id, $subjectValue, $invoice->invoice_number, $invoice->due_date->toDateString()));

                $sent++;
            }
        }

        return $sent;
    }
}
