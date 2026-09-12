<?php

namespace App\Actions\JournalEntry;

use App\Actions\Action;
use App\Enums\JournalEntryStatus;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class ApproveJournalEntry
{
    use Action;

    public function handle(JournalEntry $entry, User $approver): JournalEntry
    {
        return DB::transaction(function () use ($entry, $approver) {
            if ($entry->status !== JournalEntryStatus::Submitted) {
                throw new \RuntimeException('Only submitted entries can be approved.');
            }

            if ($entry->submitted_by === $approver->id) {
                throw new \RuntimeException('Maker-checker: The approver cannot be the same person who submitted the entry.');
            }

            if (! $entry->isBalanced()) {
                throw new \RuntimeException('Cannot approve an unbalanced entry. Debits must equal credits.');
            }

            $entry->update([
                'status' => JournalEntryStatus::Approved,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            app(AuditLogService::class)->record(
                $entry->organization,
                $approver->id,
                'journal-entry.approved',
                JournalEntry::class,
                $entry->id,
                ['status' => 'submitted'],
                ['status' => 'approved']
            );

            return $entry->fresh();
        });
    }

    public function rules(): array
    {
        return [];
    }
}
