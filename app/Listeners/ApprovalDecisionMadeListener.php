<?php

namespace App\Listeners;

use App\Events\ApprovalDecisionMade;
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
        $decision = $event->decision;

        if ($request->requested_by) {
            $user = User::find($request->requested_by);
            if ($user) {
                $user->notify(new ApprovalDecisionMadeNotification(
                    (string) $request->id,
                    $decision,
                    $request->subject ?? 'Approval Request'
                ));
            }
        }
    }
}
