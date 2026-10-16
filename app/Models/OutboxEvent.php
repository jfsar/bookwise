<?php

namespace App\Models;

use App\Enums\OutboxEventStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int|null $organization_id
 * @property-read string $aggregate_type
 * @property-read int $aggregate_id
 * @property-read int|null $aggregate_version
 * @property-read string $event_type
 * @property-read array<string, mixed> $payload
 * @property-read OutboxEventStatus $status
 * @property-read Carbon $available_at
 * @property-read Carbon|null $published_at
 * @property-read int $attempt_count
 * @property-read string|null $last_error
 */
class OutboxEvent extends Model
{
    protected $fillable = [
        'organization_id',
        'aggregate_type',
        'aggregate_id',
        'aggregate_version',
        'event_type',
        'payload',
        'status',
        'available_at',
        'published_at',
        'attempt_count',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => OutboxEventStatus::class,
            'available_at' => 'datetime',
            'published_at' => 'datetime',
            'attempt_count' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Events that are due to be relayed: still pending and past their backoff.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query
            ->where('status', OutboxEventStatus::Pending)
            ->where('available_at', '<=', now());
    }
}
