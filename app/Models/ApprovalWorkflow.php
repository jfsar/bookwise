<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property-read int $organization_id
 * @property-read string $code
 * @property-read string $name
 * @property-read string $transaction_type
 * @property-read bool $is_active
 * @property-read Organization $organization
 * @property-read Collection<int, ApprovalWorkflowStep> $steps
 * @property-read Collection<int, ApprovalRule> $rules
 */
class ApprovalWorkflow extends Model
{
    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'transaction_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowStep::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ApprovalRule::class);
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
