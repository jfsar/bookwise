<?php

namespace App\Actions\JournalEntry;

use App\Actions\Action;
use App\Enums\JournalEntryStatus;
use App\Events\JournalPosted;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class PostJournalEntry
{
    use Action;

    public function handle(JournalEntry $entry, User $poster): JournalEntry
    {
        return DB::transaction(function () use ($entry, $poster) {
            if ($entry->status !== JournalEntryStatus::Approved) {
                throw new \RuntimeException('Only approved entries can be posted.');
            }

            if ($entry->created_by === $poster->id) {
                throw new \RuntimeException('Maker-checker: The final poster cannot be the same person who created the entry.');
            }

            if (! $entry->isBalanced()) {
                throw new \RuntimeException('Cannot post an unbalanced entry. Debits must equal credits.');
            }

            $period = $entry->accountingPeriod;
            if (! $period->isOpen()) {
                throw new \RuntimeException('Cannot post to a closed accounting period.');
            }

            $entry->update([
                'status' => JournalEntryStatus::Posted,
                'posted_by' => $poster->id,
                'posted_at' => now(),
            ]);

            $entry = $entry->fresh();

            app(AuditLogService::class)->record(
                $entry->organization,
                $poster->id,
                'journal-entry.posted',
                JournalEntry::class,
                $entry->id,
                ['status' => 'approved'],
                ['status' => 'posted']
            );

            DB::afterCommit(fn () => JournalPosted::dispatch($entry));

            return $entry;
        });
    }

    public function rules(): array
    {
        return [];
    }
}
