<?php

namespace App\Actions\RecurringJournal;

use App\Actions\Action;
use App\Enums\RecurringJournalStatus;
use App\Models\RecurringJournal;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class PauseRecurringJournal
{
    use Action;

    public function handle(RecurringJournal $recurringJournal, User $actor): RecurringJournal
    {
        return DB::transaction(function () use ($recurringJournal, $actor) {
            $recurringJournal = RecurringJournal::where('id', $recurringJournal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($recurringJournal->status !== RecurringJournalStatus::Active) {
                throw new \RuntimeException('Only an active recurring journal can be paused.');
            }

            $recurringJournal->update([
                'status' => RecurringJournalStatus::Paused,
            ]);

            app(AuditLogService::class)->record(
                $recurringJournal->organization,
                $actor->id,
                'recurring-journal.paused',
                RecurringJournal::class,
                $recurringJournal->id,
                ['status' => RecurringJournalStatus::Active->value],
                ['status' => RecurringJournalStatus::Paused->value]
            );

            return $recurringJournal->fresh();
        });
    }

    public function rules(): array
    {
        return [];
    }
}
