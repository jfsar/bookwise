<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property-read int $id
 * @property-read int|null $organization_id
 * @property-read int|null $user_id
 * @property-read string $action
 * @property-read string $auditable_type
 * @property-read int|null $auditable_id
 * @property-read array<string, mixed>|null $old_values
 * @property-read array<string, mixed>|null $new_values
 * @property-read int|null $sequence_no
 * @property-read string|null $previous_hash
 * @property-read string|null $record_hash
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'user_id',
        'actor_type',
        'actor_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'request_id',
        'correlation_id',
        'sequence_no',
        'previous_hash',
        'record_hash',
        'retention_class',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
