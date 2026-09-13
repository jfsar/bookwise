<?php

namespace App\Models;

use App\Enums\ApprovalMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $organization_id
 * @property-read int $approval_workflow_id
 * @property-read int $sequence
 * @property-read string $name
 * @property-read int|null $required_role_id
 * @property-read int|null $required_permission_id
 * @property-read string|null $minimum_amount
 * @property-read string|null $maximum_amount
 * @property-read ApprovalMode $approval_mode
 * @property-read int $minimum_approvals
 * @property-read ApprovalWorkflow $workflow
 * @property-read Role|null $requiredRole
 * @property-read Permission|null $requiredPermission
 */
class ApprovalWorkflowStep extends Model
{
    protected $fillable = [
        'organization_id',
        'approval_workflow_id',
        'sequence',
        'name',
        'required_role_id',
        'required_permission_id',
        'minimum_amount',
        'maximum_amount',
        'approval_mode',
        'minimum_approvals',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'minimum_amount' => 'decimal:4',
            'maximum_amount' => 'decimal:4',
            'approval_mode' => ApprovalMode::class,
            'minimum_approvals' => 'integer',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function requiredRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'required_role_id');
    }

    public function requiredPermission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'required_permission_id');
    }
}
