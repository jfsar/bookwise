<?php

namespace App\Models;

use App\Enums\ApprovalRequestStepStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $organization_id
 * @property-read int $approval_request_id
 * @property-read int $workflow_step_id
 * @property-read ApprovalRequestStepStatus $status
 * @property-read int|null $assigned_user_id
 * @property-read int $required_approvals
 * @property-read int $approved_count
 * @property-read Carbon|null $due_at
 * @property-read Carbon|null $resolved_at
 * @property-read ApprovalRequest $request
 * @property-read ApprovalWorkflowStep $workflowStep
 */
class ApprovalRequestStep extends Model
{
    protected $fillable = [
        'organization_id',
        'approval_request_id',
        'workflow_step_id',
        'status',
        'assigned_user_id',
        'required_approvals',
        'approved_count',
        'due_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApprovalRequestStepStatus::class,
            'required_approvals' => 'integer',
            'approved_count' => 'integer',
            'due_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflowStep::class, 'workflow_step_id');
    }
}
