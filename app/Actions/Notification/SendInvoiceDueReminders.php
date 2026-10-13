<?php

namespace App\Actions\Notification;

use App\Actions\Action;
use App\Actions\Notification\Concerns\SkipsDuplicateReminders;
use App\Enums\CustomerInvoiceStatus;
use App\Models\CustomerInvoice;
use App\Models\User;
use App\Notifications\InvoiceDueSoon;
use App\Notifications\InvoiceOverdue;
use Illuminate\Database\Eloquent\Collection;
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

        $sent = 0;
        /** @var array<int, Collection<int, User>> $recipientsByOrganization */
        $recipientsByOrganization = [];

        CustomerInvoice::query()
            ->with('organization')
            ->whereNotNull('due_date')
            ->whereIn('status', [CustomerInvoiceStatus::Posted, CustomerInvoiceStatus::PartiallyPaid])
            ->whereDate('due_date', '<=', $dueSoonBy->toDateString())
            ->chunkById(200, function ($invoices) use (&$sent, &$recipientsByOrganization, $today) {
                foreach ($invoices as $invoice) {
                    $organization = $invoice->organization;

                    if (! $organization) {
                        continue;
                    }

                    $recipients = $recipientsByOrganization[$organization->id]
                        ??= User::membersWithPermission($organization, 'view-invoices');
                    $overdue = $invoice->due_date->startOfDay()->lt($today);
                    $subjectValue = $invoice->ulid ?? (string) $invoice->getKey();

                    foreach ($recipients as $user) {
                        $type = $overdue ? InvoiceOverdue::class : InvoiceDueSoon::class;

                        if ($this->alreadyRemindedToday($user, $organization, $type, 'invoice_id', $subjectValue)) {
                            continue;
                        }

                        $user->notify($overdue
                            ? new InvoiceOverdue($organization->id, $subjectValue, $invoice->invoice_number, $invoice->due_date->toDateString())
                            : new InvoiceDueSoon($organization->id, $subjectValue, $invoice->invoice_number, $invoice->due_date->toDateString()));

                        $sent++;
                    }
                }
            });

        return $sent;
    }
}
