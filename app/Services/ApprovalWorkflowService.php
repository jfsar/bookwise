<?php

namespace App\Services;

use App\Contracts\ApprovalSubject;
use App\Enums\ApprovalActionType;
use App\Enums\ApprovalMode;
use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestStepStatus;
use App\Models\ApprovalAction;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRequestStep;
use App\Models\ApprovalRule;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApprovalWorkflowService
{
    /**
     * Resolve the active approval workflow that governs the given transaction.
     *
     * @param  array<int>  $accountIds
     */
    public function resolve(Organization $organization, string $transactionType, string|float $amount, array $accountIds = []): ?ApprovalWorkflow
    {
        $amount = (float) $amount;

        $rules = ApprovalRule::query()
            ->forOrganization($organization)
            ->active()
            ->where('transaction_type', $transactionType)
            ->with('workflow')
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            $workflow = $rule->getRelation('workflow');
            if (! $workflow?->is_active) {
                continue;
            }

            if ($rule->minimum_amount !== null && $amount < (float) $rule->minimum_amount) {
                continue;
            }

            if ($rule->maximum_amount !== null && $amount > (float) $rule->maximum_amount) {
                continue;
            }

            if ($rule->account_id !== null && ! in_array($rule->account_id, $accountIds, true)) {
                continue;
            }

            return $workflow;
        }

        return null;
    }

    /**
     * Create an approval request for the subject when an active workflow applies.
     * Returns null when no workflow governs the transaction (direct approval applies).
     */
    public function createRequest(ApprovalSubject $subject, User $requester, string $transactionType): ?ApprovalRequest
    {
        $organization = $subject->approvalOrganization();
        $amount = $subject->approvalAmount();
        $workflow = $this->resolve($organization, $transactionType, $amount, $subject->approvalAccountIds());

        if ($workflow === null) {
            return null;
        }

        return DB::transaction(function () use ($workflow, $subject, $requester, $organization, $amount) {
            $steps = $workflow->steps()
                ->orderBy('sequence')
                ->get()
                ->filter(fn (ApprovalWorkflowStep $step) => $this->stepApplies($step, (float) $amount))
                ->values();

            if ($steps->isEmpty()) {
                return null;
            }

            $request = ApprovalRequest::create([
                'organization_id' => $organization->id,
                'requestable_type' => $subject::class,
                'requestable_id' => $subject->getKey(),
                'requested_by' => $requester->id,
                'assigned_to' => null,
                'required_role_id' => $steps->first()->required_role_id,
                'amount' => $amount,
                'status' => ApprovalRequestStatus::InReview,
                'submitted_at' => now(),
            ]);

            foreach ($steps as $step) {
                ApprovalRequestStep::create([
                    'organization_id' => $organization->id,
                    'approval_request_id' => $request->id,
                    'workflow_step_id' => $step->id,
                    'status' => ApprovalRequestStepStatus::Pending,
                    'assigned_user_id' => null,
                    'required_approvals' => $step->minimum_approvals,
                    'approved_count' => 0,
                ]);
            }

            $this->audit($organization, $requester->id, 'approval-request.created', $request, null, [
                'workflow_id' => $workflow->id,
                'workflow_code' => $workflow->code,
                'step_count' => $steps->count(),
            ]);

            return $request->fresh(['steps']);
        });
    }

    /**
     * Record an approval or rejection decision against the open request.
     */
    public function decide(ApprovalRequest $request, User $actor, ApprovalActionType $decision, ?string $comment = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $actor, $decision, $comment) {
            $request = $this->lockRequest($request);
            $request->loadMissing(['steps.workflowStep', 'requestable']);

            $this->assertRequestOpen($request);
            $this->assertActorMayAct($request, $actor);

            $step = $this->actionableStep($request);

            if ($decision === ApprovalActionType::Reject) {
                return $this->reject($request, $actor, $step, $comment);
            }

            if ($decision !== ApprovalActionType::Approve) {
                throw new RuntimeException('Unsupported approval decision.');
            }

            return $this->approve($request, $actor, $step, $comment);
        });
    }

    /**
     * Withdraw an open request. Only the requester may withdraw their own request.
     */
    public function withdraw(ApprovalRequest $request, User $actor): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $actor) {
            $request = $this->lockRequest($request);
            $request->loadMissing('steps');

            $this->assertRequestOpen($request);

            if ($request->requested_by !== $actor->id) {
                throw new RuntimeException('Only the requester can withdraw their own approval request.');
            }

            $request->steps()->where('status', ApprovalRequestStepStatus::Pending)->update([
                'status' => ApprovalRequestStepStatus::Skipped,
            ]);

            $request->update([
                'status' => ApprovalRequestStatus::Withdrawn,
                'resolved_at' => now(),
            ]);

            $this->audit($request->organization, $actor->id, 'approval-request.withdrawn', $request, null, null);

            return $request->fresh(['steps']);
        });
    }

    /**
     * Cancel an open request because the subject is being cancelled.
     */
    public function cancel(ApprovalRequest $request, User $actor, ?string $note = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $actor, $note) {
            $request = $this->lockRequest($request);
            $request->loadMissing('steps');

            $this->assertRequestOpen($request);

            $request->steps()->where('status', ApprovalRequestStepStatus::Pending)->update([
                'status' => ApprovalRequestStepStatus::Skipped,
            ]);

            $request->update([
                'status' => ApprovalRequestStatus::Cancelled,
                'resolved_at' => now(),
                'resolution_note' => $note,
            ]);

            $this->audit($request->organization, $actor->id, 'approval-request.cancelled', $request, null, null);

            return $request->fresh(['steps']);
        });
    }

    private function stepApplies(ApprovalWorkflowStep $step, float $amount): bool
    {
        if ($step->minimum_amount !== null && $amount < (float) $step->minimum_amount) {
            return false;
        }

        if ($step->maximum_amount !== null && $amount > (float) $step->maximum_amount) {
            return false;
        }

        return true;
    }

    private function lockRequest(ApprovalRequest $request): ApprovalRequest
    {
        return ApprovalRequest::query()
            ->whereKey($request->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertRequestOpen(ApprovalRequest $request): void
    {
        if (! in_array($request->status, [ApprovalRequestStatus::Pending, ApprovalRequestStatus::InReview], true)) {
            throw new RuntimeException('This approval request is no longer open.');
        }
    }

    private function assertActorMayAct(ApprovalRequest $request, User $actor): void
    {
        if ($actor->id === $request->requested_by) {
            throw new RuntimeException('Maker-checker: The approver cannot be the same person who submitted the entry.');
        }

        if (! $actor->belongsToOrganization($request->organization)) {
            throw new RuntimeException('The approving user is not a member of the organization.');
        }
    }

    private function actionableStep(ApprovalRequest $request): ApprovalRequestStep
    {
        $steps = $request->steps
            ->sortBy(fn (ApprovalRequestStep $step) => $step->workflowStep?->sequence ?? $step->id)
            ->values();

        foreach ($steps as $index => $step) {
            if ($step->status !== ApprovalRequestStepStatus::Pending) {
                continue;
            }

            $priorStepsApproved = $steps
                ->take($index)
                ->every(fn (ApprovalRequestStep $prior) => $prior->status === ApprovalRequestStepStatus::Approved);

            if (! $priorStepsApproved) {
                continue;
            }

            return $step;
        }

        throw new RuntimeException('This approval request has no actionable step.', 409);
    }

    private function assertActorEligibleForStep(ApprovalRequest $request, User $actor, ApprovalRequestStep $step): void
    {
        $workflowStep = $step->workflowStep;

        if ($step->assigned_user_id !== null && $step->assigned_user_id !== $actor->id) {
            throw new RuntimeException('This approval step is assigned to another user.');
        }

        if ($workflowStep?->required_role_id !== null) {
            $role = $workflowStep->requiredRole;
            if ($role !== null && ! $actor->hasRole($role->slug, $request->organization)) {
                throw new RuntimeException('The approving user does not hold the required role for this approval step.');
            }
        }

        if ($workflowStep?->required_permission_id !== null) {
            $permission = $workflowStep->requiredPermission;
            if ($permission !== null && ! $actor->hasPermissionTo($permission->name, $request->organization)) {
                throw new RuntimeException('The approving user does not have the required permission for this approval step.');
            }
        }
    }

    private function assertActorHasNotApprovedStep(ApprovalRequest $request, User $actor, ApprovalRequestStep $step): void
    {
        $alreadyApproved = ApprovalAction::query()
            ->where('approval_request_id', $request->id)
            ->where('approval_request_step_id', $step->id)
            ->where('user_id', $actor->id)
            ->where('action', ApprovalActionType::Approve)
            ->exists();

        if ($alreadyApproved) {
            throw new RuntimeException('The approving user has already approved this step.');
        }
    }

    private function approve(ApprovalRequest $request, User $actor, ApprovalRequestStep $step, ?string $comment): ApprovalRequest
    {
        $this->assertActorEligibleForStep($request, $actor, $step);
        $this->assertActorHasNotApprovedStep($request, $actor, $step);
        $this->recordAction($request, $step, $actor, ApprovalActionType::Approve, $comment);

        $isParallel = $step->workflowStep?->approval_mode === ApprovalMode::Parallel;
        $approvals = $isParallel ? $step->approved_count + 1 : 1;
        $isResolved = $approvals >= $step->required_approvals;

        $step->update([
            'approved_count' => $isParallel ? $approvals : 0,
            'status' => $isResolved ? ApprovalRequestStepStatus::Approved : ApprovalRequestStepStatus::Pending,
            'resolved_at' => $isResolved ? now() : null,
        ]);

        $this->audit($request->organization, $actor->id, 'approval-request.step-approved', $request, $step, [
            'approved_count' => $approvals,
        ]);

        if (! $isResolved) {
            return $request->fresh(['steps']);
        }

        $steps = $request->steps()->get();
        if ($steps->every(fn (ApprovalRequestStep $pending) => $pending->status === ApprovalRequestStepStatus::Approved)) {
            $request->update([
                'status' => ApprovalRequestStatus::Approved,
                'resolved_at' => now(),
            ]);

            $this->audit($request->organization, $actor->id, 'approval-request.approved', $request, $step);

            $subject = $request->requestable;
            if ($subject instanceof ApprovalSubject) {
                $subject->approveBy($actor, $comment);
            }
        }

        return $request->fresh(['steps']);
    }

    private function reject(ApprovalRequest $request, User $actor, ApprovalRequestStep $step, ?string $comment): ApprovalRequest
    {
        if (blank($comment)) {
            throw new RuntimeException('Rejection requires a reason.');
        }

        $this->assertActorEligibleForStep($request, $actor, $step);
        $this->recordAction($request, $step, $actor, ApprovalActionType::Reject, $comment);

        $request->steps()
            ->where('status', ApprovalRequestStepStatus::Pending)
            ->whereKeyNot($step->getKey())
            ->update(['status' => ApprovalRequestStepStatus::Skipped]);

        $request->steps()->find($step->getKey())?->update([
            'status' => ApprovalRequestStepStatus::Rejected,
            'resolved_at' => now(),
        ]);

        $request->update([
            'status' => ApprovalRequestStatus::Rejected,
            'resolved_at' => now(),
            'resolution_note' => $comment,
        ]);

        $this->audit($request->organization, $actor->id, 'approval-request.rejected', $request, $step, [
            'reason' => $comment,
        ]);

        $subject = $request->requestable;
        if ($subject instanceof ApprovalSubject) {
            $subject->rejectBy($actor, $comment);
        }

        return $request->fresh(['steps']);
    }

    private function recordAction(ApprovalRequest $request, ApprovalRequestStep $step, User $actor, ApprovalActionType $action, ?string $comment): void
    {
        ApprovalAction::create([
            'organization_id' => $request->organization_id,
            'approval_request_id' => $request->id,
            'approval_request_step_id' => $step->id,
            'user_id' => $actor->id,
            'action' => $action,
            'comment' => $comment,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function audit(Organization $organization, int $userId, string $action, ApprovalRequest $request, ?ApprovalRequestStep $step = null, ?array $metadata = null): void
    {
        app(AuditLogService::class)->record(
            $organization,
            $userId,
            $action,
            ApprovalRequest::class,
            $request->id,
            null,
            array_merge($metadata ?? [], $step !== null ? ['approval_request_step_id' => $step->id] : [])
        );
    }
}
