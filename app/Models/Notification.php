<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'organization_id', 'read_at'])]
class Notification extends Model
{
    /** @var bool */
    public $incrementing = false;

    /** @var string */
    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'data' => 'array',
        ];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function markAsRead(): void
    {
        if (is_null($this->read_at)) {
            $this->update(['read_at' => now()]);
        }
    }

    public function markAsUnread(): void
    {
        $this->update(['read_at' => null]);
    }

    public function isRead(): bool
    {
        return ! is_null($this->read_at);
    }

    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeForOrganization($query, Organization $organization)
    {
        return $query->where('organization_id', $organization->id);
    }
}
