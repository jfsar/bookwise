<?php

namespace App\Actions\JournalEntry;

use App\Actions\Action;
use App\Enums\JournalEntryStatus;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubmitJournalEntry
{
    use Action;

    public function handle(JournalEntry $entry, User $submitter): JournalEntry
    {
        return DB::transaction(function () use ($entry, $submitter) {
            if (! $entry->isDraft()) {
                throw new \RuntimeException('Only draft entries can be submitted.');
            }

            if (! $entry->isBalanced()) {
                throw new \RuntimeException('Cannot submit an unbalanced entry. Debits must equal credits.');
            }

            $entry->update([
                'status' => JournalEntryStatus::Submitted,
                'submitted_by' => $submitter->id,
                'submitted_at' => now(),
            ]);

            return $entry->fresh();
        });
    }

    public function rules(): array
    {
        return [];
    }
}
