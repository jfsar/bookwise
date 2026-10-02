<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'organization_id',
    'attachable_type',
    'attachable_id',
    'original_name',
    'stored_name',
    'disk',
    'path',
    'mime_type',
    'size',
    'checksum',
    'visibility',
    'scan_status',
    'scan_result',
    'content_hash',
    'uploaded_by',
    'uploaded_at',
])]
class Attachment extends Model
{
    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
        ];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeForOrganization($query, Organization $organization)
    {
        return $query->where('organization_id', $organization->id);
    }

    public function scopePendingScan($query)
    {
        return $query->where('scan_status', 'pending');
    }

    public function scopeClean($query)
    {
        return $query->where('scan_status', 'clean');
    }

    public function scopeInfected($query)
    {
        return $query->where('scan_status', 'infected');
    }

    public function isPrivate(): bool
    {
        return $this->visibility === 'private';
    }
}
