<?php

namespace App\Listeners;

use App\Events\ApprovalRequestCreated;
use App\Models\User;
use App\Notifications\ApprovalRequestCreated as ApprovalRequestCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ApprovalRequestCreatedListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(ApprovalRequestCreated $event): void
    {
        $request = $event->approvalRequest;

        if (! $request->assigned_to) {
            $roleId = $request->required_role_id;
            if ($roleId) {
                User::whereHas('roles', function ($q) use ($roleId, $request) {
                    $q->where('roles.id', $roleId)
                        ->where('role_user.organization_id', $request->organization_id);
                })
                    ->get()
                    ->each(function ($user) use ($request) {
                        $user->notify(new ApprovalRequestCreatedNotification((string) $request->id, $request->subject ?? 'Approval Request'));
                    });
            }
        } else {
            $user = User::find($request->assigned_to);
            if ($user) {
                $user->notify(new ApprovalRequestCreatedNotification((string) $request->id, $request->subject ?? 'Approval Request'));
            }
        }
    }
}
