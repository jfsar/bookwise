<?php

namespace App\Actions\RecurringJournal;

use App\Actions\Action;
use App\Enums\RecurringJournalStatus;
use App\Models\RecurringJournal;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class DeleteRecurringJournal
{
    use Action;

    public function handle(RecurringJournal $recurringJournal, User $actor): void
    {
        DB::transaction(function () use ($recurringJournal, $actor) {
            $recurringJournal = RecurringJournal::where('id', $recurringJournal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($recurringJournal->status === RecurringJournalStatus::Active) {
                throw new \RuntimeException('Pause the recurring journal before deleting it.');
            }

            if ($recurringJournal->executions()->exists()) {
                throw new \RuntimeException('This recurring journal already produced journal entries and cannot be deleted. Pause it to stop future runs.');
            }

            app(AuditLogService::class)->record(
                $recurringJournal->organization,
                $actor->id,
                'recurring-journal.deleted',
                RecurringJournal::class,
                $recurringJournal->id,
                ['name' => $recurringJournal->name, 'status' => $recurringJournal->status->value],
                null
            );

            $recurringJournal->lines()->delete();
            $recurringJournal->delete();
        });
    }

    public function rules(): array
    {
        return [];
    }
}
