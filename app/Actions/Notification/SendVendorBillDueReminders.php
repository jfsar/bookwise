<?php

namespace App\Actions\Notification;

use App\Actions\Action;
use App\Actions\Notification\Concerns\SkipsDuplicateReminders;
use App\Enums\VendorBillStatus;
use App\Models\User;
use App\Models\VendorBill;
use App\Notifications\VendorBillDue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Reminds staff who can see bills about posted/partially paid bills falling due.
 * Notifications are advisory only — they never affect bill state.
 */
class SendVendorBillDueReminders
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

        VendorBill::query()
            ->with('organization')
            ->whereNotNull('due_date')
            ->whereIn('status', [VendorBillStatus::Posted, VendorBillStatus::PartiallyPaid])
            ->whereDate('due_date', '<=', $dueSoonBy->toDateString())
            ->chunkById(200, function ($bills) use (&$sent, &$recipientsByOrganization) {
                foreach ($bills as $bill) {
                    $organization = $bill->organization;

                    if (! $organization) {
                        continue;
                    }

                    $recipients = $recipientsByOrganization[$organization->id]
                        ??= User::membersWithPermission($organization, 'view-bills');
                    $subjectValue = $bill->ulid ?? (string) $bill->getKey();

                    foreach ($recipients as $user) {
                        if ($this->alreadyRemindedToday($user, $organization, VendorBillDue::class, 'bill_id', $subjectValue)) {
                            continue;
                        }

                        $user->notify(new VendorBillDue(
                            $organization->id,
                            $subjectValue,
                            $bill->bill_number,
                            $bill->due_date->toDateString()
                        ));

                        $sent++;
                    }
                }
            });

        return $sent;
    }
}
