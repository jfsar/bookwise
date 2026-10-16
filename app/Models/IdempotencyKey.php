<?php

namespace App\Models;

use App\Enums\IdempotencyStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $organization_id
 * @property-read string $key
 * @property-read string $operation_type
 * @property-read string $request_hash
 * @property-read IdempotencyStatus $status
 * @property-read string|null $resource_type
 * @property-read int|null $resource_id
 * @property-read int|null $response_code
 * @property-read array<string, mixed>|null $response_payload
 * @property-read Carbon|null $expires_at
 */
class IdempotencyKey extends Model
{
    protected $fillable = [
        'organization_id',
        'key',
        'operation_type',
        'request_hash',
        'status',
        'resource_type',
        'resource_id',
        'response_code',
        'response_payload',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => IdempotencyStatus::class,
            'response_payload' => 'array',
            'response_code' => 'integer',
            'resource_id' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
