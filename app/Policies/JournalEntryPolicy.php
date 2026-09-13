<?php

namespace App\Policies;

use App\Models\JournalEntry;
use App\Models\Organization;
use App\Models\User;

class JournalEntryPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('view-journal-entries', $organization);
    }

    public function view(User $user, JournalEntry $journalEntry): bool
    {
        return $user->hasPermissionTo('view-journal-entries', $journalEntry->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('create-journal-entries', $organization);
    }

    public function update(User $user, JournalEntry $journalEntry): bool
    {
        return $user->hasPermissionTo('edit-journal-entries', $journalEntry->organization)
            && $journalEntry->isDraft();
    }

    public function delete(User $user, JournalEntry $journalEntry): bool
    {
        return $user->hasPermissionTo('delete-journal-entries', $journalEntry->organization)
            && $journalEntry->isDraft();
    }

    public function submit(User $user, JournalEntry $journalEntry): bool
    {
        return $user->hasPermissionTo('submit-journal-entries', $journalEntry->organization)
            && $journalEntry->isDraft();
    }

    public function approve(User $user, JournalEntry $journalEntry): bool
    {
        return $user->hasPermissionTo('approve-journal-entries', $journalEntry->organization)
            && $journalEntry->status->value === 'submitted'
            && $journalEntry->submitted_by !== $user->id;
    }

    public function reject(User $user, JournalEntry $journalEntry): bool
    {
        return $user->hasPermissionTo('reject-journal-entries', $journalEntry->organization)
            && $journalEntry->status->value === 'submitted'
            && $journalEntry->submitted_by !== $user->id;
    }

    public function post(User $user, JournalEntry $journalEntry): bool
    {
        return $user->hasPermissionTo('post-journal-entries', $journalEntry->organization)
            && $journalEntry->status->value === 'approved'
            && $journalEntry->created_by !== $user->id;
    }

    public function reverse(User $user, JournalEntry $journalEntry): bool
    {
        return $user->hasPermissionTo('reverse-journal-entries', $journalEntry->organization)
            && $journalEntry->isPosted();
    }

    public function cancel(User $user, JournalEntry $journalEntry): bool
    {
        return $user->hasPermissionTo('cancel-journal-entries', $journalEntry->organization)
            && in_array($journalEntry->status->value, ['draft', 'submitted']);
    }
}
