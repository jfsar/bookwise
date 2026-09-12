<?php

namespace App\Actions\JournalEntry;

use App\Actions\Action;
use App\Enums\JournalEntryStatus;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class CancelJournalEntry
{
    use Action;

    public function handle(JournalEntry $entry, User $canceller, string $reason): JournalEntry
    {
        return DB::transaction(function () use ($entry, $canceller, $reason) {
            if (! in_array($entry->status, [JournalEntryStatus::Draft, JournalEntryStatus::Submitted])) {
                throw new \RuntimeException('Only draft or submitted entries can be cancelled.');
            }

            $previousStatus = $entry->status->value;

            $entry->update([
                'status' => JournalEntryStatus::Cancelled,
                'rejection_reason' => $reason,
                'updated_by' => $canceller->id,
            ]);

            app(AuditLogService::class)->record(
                $entry->organization,
                $canceller->id,
                'journal-entry.cancelled',
                JournalEntry::class,
                $entry->id,
                ['status' => $previousStatus],
                ['status' => 'cancelled']
            );

            return $entry->fresh();
        });
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
