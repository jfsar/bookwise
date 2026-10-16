<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property-read int $organization_id
 * @property-read int $bank_account_id
 * @property-read string $source
 * @property-read string $file_name
 * @property-read string|null $file_checksum
 * @property-read string $status
 * @property-read int $rows_read
 * @property-read int $rows_imported
 * @property-read int $rows_skipped
 * @property-read int $rows_failed
 * @property-read string|null $error_summary
 * @property-read int $created_by
 * @property-read BankAccount $bankAccount
 * @property-read User $creator
 */
class BankImportBatch extends Model
{
    protected $fillable = [
        'organization_id',
        'bank_account_id',
        'source',
        'file_name',
        'file_checksum',
        'started_at',
        'completed_at',
        'status',
        'rows_read',
        'rows_imported',
        'rows_skipped',
        'rows_failed',
        'error_summary',
        'created_by',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class, 'import_batch_id');
    }

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }
}
