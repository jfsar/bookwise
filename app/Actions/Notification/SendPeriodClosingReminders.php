<?php

namespace App\Actions\Notification;

use App\Actions\Action;
use App\Actions\Notification\Concerns\SkipsDuplicateReminders;
use App\Models\AccountingPeriod;
use App\Models\User;
use App\Notifications\PeriodClosingReminder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Reminds staff who can close periods about open periods that are about to end.
 * Notifications are advisory only — they never close or reopen a period.
 */
class SendPeriodClosingReminders
{
    use Action;
    use SkipsDuplicateReminders;

    public function handle(?Carbon $asOf = null, int $leadDays = 7): int
    {
        $today = ($asOf ?? Carbon::now())->copy()->startOfDay();
        $closingBy = $today->copy()->addDays($leadDays);

        $sent = 0;
        /** @var array<int, Collection<int, User>> $recipientsByOrganization */
        $recipientsByOrganization = [];

        AccountingPeriod::query()
            ->with('organization')
            ->open()
            ->whereDate('end_date', '<=', $closingBy->toDateString())
            ->whereDate('end_date', '>=', $today->toDateString())
            ->chunkById(200, function ($periods) use (&$sent, &$recipientsByOrganization) {
                foreach ($periods as $period) {
                    $organization = $period->organization;

                    if (! $organization) {
                        continue;
                    }

                    $recipients = $recipientsByOrganization[$organization->id]
                        ??= User::membersWithPermission($organization, 'close-accounting-period');
                    $subjectValue = $period->ulid ?? (string) $period->getKey();

                    foreach ($recipients as $user) {
                        if ($this->alreadyRemindedToday($user, $organization, PeriodClosingReminder::class, 'period_id', $subjectValue)) {
                            continue;
                        }

                        $user->notify(new PeriodClosingReminder(
                            $organization->id,
                            $subjectValue,
                            $period->name,
                            $period->end_date->toDateString()
                        ));

                        $sent++;
                    }
                }
            });

        return $sent;
    }
}
