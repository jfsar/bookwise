<?php

namespace App\Listeners;

use App\Events\ApprovalRequestCreated;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ApprovalRequestCreated as ApprovalRequestCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Queue\InteractsWithQueue;

class ApprovalRequestCreatedListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(ApprovalRequestCreated $event): void
    {
        $request = $event->approvalRequest;
        $organization = Organization::find($request->organization_id);

        if (! $organization) {
            return;
        }

        $recipients = $request->assigned_to
            ? $this->assignedApprover($request->assigned_to, $organization)
            : $this->roleHolders($request->required_role_id, $organization);

        $notification = new ApprovalRequestCreatedNotification(
            $organization->id,
            (string) $request->getKey(),
            $request->subjectLabel()
        );

        $recipients->each(fn (User $user) => $user->notify($notification));
    }

    /**
     * @return Collection<int, User>
     */
    private function assignedApprover(int $userId, Organization $organization): Collection
    {
        return User::query()
            ->whereKey($userId)
            ->activeMembersOf($organization)
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function roleHolders(?int $roleId, Organization $organization): Collection
    {
        if (! $roleId) {
            return new Collection;
        }

        return User::query()
            ->activeMembersOf($organization)
            ->whereHas('roles', fn ($query) => $query
                ->where('role_user.organization_id', $organization->id)
                ->where('roles.id', $roleId))
            ->get();
    }
}
