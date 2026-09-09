<?php

namespace App\Models;

use App\Enums\FiscalYearStatus;
use App\Models\Concerns\HasUlidIdentifier;
use Carbon\Carbon;
use Database\Factories\FiscalYearFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property-read string $ulid
 * @property-read int $organization_id
 * @property-read string $name
 * @property-read Carbon $start_date
 * @property-read Carbon $end_date
 * @property-read FiscalYearStatus $status
 * @property-read int|null $close_journal_entry_id
 * @property-read Carbon|null $closed_at
 * @property-read int|null $closed_by
 */
class FiscalYear extends Model
{
    /** @use HasFactory<FiscalYearFactory> */
    use HasFactory, HasUlidIdentifier;

    protected $fillable = [
        'organization_id',
        'name',
        'start_date',
        'end_date',
        'status',
        'close_journal_entry_id',
        'closed_at',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => FiscalYearStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class);
    }

    public function closeJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'close_journal_entry_id');
    }

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', FiscalYearStatus::Open);
    }

    public function isActive(): bool
    {
        return $this->status === FiscalYearStatus::Open;
    }
}
