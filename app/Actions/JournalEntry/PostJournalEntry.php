<?php

namespace App\Actions\JournalEntry;

use App\Actions\Action;
use App\Enums\JournalEntryStatus;
use App\Models\JournalEntry;
use App\Models\User;
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

            return $entry->fresh();
        });
    }

    public function rules(): array
    {
        return [];
    }
}
