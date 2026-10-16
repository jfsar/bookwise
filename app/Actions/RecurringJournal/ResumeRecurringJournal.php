<?php

namespace App\Actions\RecurringJournal;

use App\Actions\Action;
use App\Enums\RecurringJournalStatus;
use App\Models\RecurringJournal;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class ResumeRecurringJournal
{
    use Action;

    public function handle(RecurringJournal $recurringJournal, User $actor): RecurringJournal
    {
        return DB::transaction(function () use ($recurringJournal, $actor) {
            $recurringJournal = RecurringJournal::where('id', $recurringJournal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($recurringJournal->status !== RecurringJournalStatus::Paused) {
                throw new \RuntimeException('Only a paused recurring journal can be resumed.');
            }

            $recurringJournal->update([
                'status' => RecurringJournalStatus::Active,
            ]);

            app(AuditLogService::class)->record(
                $recurringJournal->organization,
                $actor->id,
                'recurring-journal.resumed',
                RecurringJournal::class,
                $recurringJournal->id,
                ['status' => RecurringJournalStatus::Paused->value],
                ['status' => RecurringJournalStatus::Active->value]
            );

            return $recurringJournal->fresh();
        });
    }

    public function rules(): array
    {
        return [];
    }
}
