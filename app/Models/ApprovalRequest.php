<?php

namespace App\Models;

use App\Enums\ApprovalRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $organization_id
 * @property-read string $requestable_type
 * @property-read int $requestable_id
 * @property-read int $requested_by
 * @property-read int|null $assigned_to
 * @property-read int|null $required_role_id
 * @property-read string|null $amount
 * @property-read ApprovalRequestStatus $status
 * @property-read Carbon $submitted_at
 * @property-read Carbon|null $resolved_at
 * @property-read string|null $resolution_note
 * @property-read Model|null $requestable
 * @property-read User $requester
 * @property-read Collection<int, ApprovalRequestStep> $steps
 * @property-read Collection<int, ApprovalAction> $actions
 */
class ApprovalRequest extends Model
{
    protected $fillable = [
        'organization_id',
        'requestable_type',
        'requestable_id',
        'requested_by',
        'assigned_to',
        'required_role_id',
        'amount',
        'status',
        'submitted_at',
        'resolved_at',
        'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'status' => ApprovalRequestStatus::class,
            'submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function requestable(): MorphTo
    {
        return $this->morphTo();
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalRequestStep::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class);
    }

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }

    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query->where('requestable_type', $subject::class)
            ->where('requestable_id', $subject->getKey());
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [ApprovalRequestStatus::Pending, ApprovalRequestStatus::InReview]);
    }

    /**
     * A human-readable label for the requestable record, used as the subject in
     * approval notifications and audit entries.
     */
    public function subjectLabel(): string
    {
        $subject = $this->requestable;

        if ($subject === null) {
            return class_basename($this->requestable_type).' #'.$this->requestable_id;
        }

        foreach (['entry_number', 'invoice_number', 'bill_number', 'name', 'description'] as $attribute) {
            $value = $subject->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return class_basename($subject::class).' '.$value;
            }
        }

        return class_basename($subject::class).' #'.$subject->getKey();
    }
}
