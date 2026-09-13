<?php

namespace App\Models;

use App\Enums\ApprovalActionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $organization_id
 * @property-read int $approval_request_id
 * @property-read int|null $approval_request_step_id
 * @property-read int $user_id
 * @property-read ApprovalActionType $action
 * @property-read string|null $comment
 * @property-read Carbon $created_at
 * @property-read ApprovalRequest $request
 * @property-read ApprovalRequestStep $requestStep
 * @property-read User $user
 */
class ApprovalAction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'approval_request_id',
        'approval_request_step_id',
        'user_id',
        'action',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'action' => ApprovalActionType::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function requestStep(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequestStep::class, 'approval_request_step_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
