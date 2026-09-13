<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $organization_id
 * @property-read int $workflow_id
 * @property-read int $priority
 * @property-read string $transaction_type
 * @property-read string|null $minimum_amount
 * @property-read string|null $maximum_amount
 * @property-read int|null $account_id
 * @property-read int|null $role_id
 * @property-read bool $is_active
 * @property-read ApprovalWorkflow $workflow
 * @property-read Account|null $account
 * @property-read Role|null $role
 */
class ApprovalRule extends Model
{
    protected $fillable = [
        'organization_id',
        'workflow_id',
        'priority',
        'transaction_type',
        'minimum_amount',
        'maximum_amount',
        'account_id',
        'role_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'minimum_amount' => 'decimal:4',
            'maximum_amount' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
