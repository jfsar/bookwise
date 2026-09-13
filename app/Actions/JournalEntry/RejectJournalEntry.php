<?php

namespace App\Actions\JournalEntry;

use App\Actions\Action;
use App\Enums\ApprovalActionType;
use App\Enums\JournalEntryStatus;
use App\Models\ApprovalRequest;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class RejectJournalEntry
{
    use Action;

    public function handle(JournalEntry $entry, User $rejecter, string $reason): JournalEntry
    {
        return DB::transaction(function () use ($entry, $rejecter, $reason) {
            if ($entry->status !== JournalEntryStatus::Submitted) {
                throw new \RuntimeException('Only submitted entries can be rejected.');
            }

            if ($entry->submitted_by === $rejecter->id) {
                throw new \RuntimeException('Maker-checker: The rejecter cannot be the same person who submitted the entry.');
            }

            $request = ApprovalRequest::forSubject($entry)
                ->open()
                ->latest('id')
                ->first();

            if ($request) {
                app(ApprovalWorkflowService::class)->decide($request, $rejecter, ApprovalActionType::Reject, $reason);

                return $entry->fresh();
            }

            $entry->update([
                'status' => JournalEntryStatus::Rejected,
                'rejection_reason' => $reason,
                'updated_by' => $rejecter->id,
            ]);

            app(AuditLogService::class)->record(
                $entry->organization,
                $rejecter->id,
                'journal-entry.rejected',
                JournalEntry::class,
                $entry->id,
                ['status' => 'submitted'],
                ['status' => 'rejected']
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
