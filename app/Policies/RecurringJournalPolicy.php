<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\RecurringJournal;
use App\Models\User;

class RecurringJournalPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('view-recurring-journals', $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('create-recurring-journals', $organization);
    }

    public function update(User $user, RecurringJournal $recurringJournal): bool
    {
        return $user->hasPermissionTo('update-recurring-journals', $recurringJournal->organization)
            && ! $recurringJournal->isCompleted();
    }

    public function delete(User $user, RecurringJournal $recurringJournal): bool
    {
        return $user->hasPermissionTo('delete-recurring-journals', $recurringJournal->organization)
            && ! $recurringJournal->isActive();
    }

    public function pause(User $user, RecurringJournal $recurringJournal): bool
    {
        return $user->hasPermissionTo('update-recurring-journals', $recurringJournal->organization)
            && $recurringJournal->isActive();
    }

    public function resume(User $user, RecurringJournal $recurringJournal): bool
    {
        return $user->hasPermissionTo('update-recurring-journals', $recurringJournal->organization)
            && $recurringJournal->isPaused();
    }

    public function run(User $user, RecurringJournal $recurringJournal): bool
    {
        return $user->hasPermissionTo('update-recurring-journals', $recurringJournal->organization)
            && $recurringJournal->isActive();
    }
}
