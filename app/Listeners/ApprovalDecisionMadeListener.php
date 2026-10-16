<?php

namespace App\Listeners;

use App\Events\ApprovalDecisionMade;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ApprovalDecisionMade as ApprovalDecisionMadeNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ApprovalDecisionMadeListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(ApprovalDecisionMade $event): void
    {
        $request = $event->approvalRequest;
        $organization = Organization::find($request->organization_id);

        if (! $organization) {
            return;
        }

        $requester = User::query()
            ->whereKey($request->requested_by)
            ->activeMembersOf($organization)
            ->first();

        if (! $requester) {
            return;
        }

        $requester->notify(new ApprovalDecisionMadeNotification(
            $organization->id,
            (string) $request->getKey(),
            $event->decision,
            $request->subjectLabel()
        ));
    }
}
