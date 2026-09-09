<?php

namespace App\Models;

use App\Enums\AccountingPeriodStatus;
use App\Models\Concerns\HasUlidIdentifier;
use Carbon\Carbon;
use Database\Factories\AccountingPeriodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read string $ulid
 * @property-read int $fiscal_year_id
 * @property-read int $organization_id
 * @property-read string $name
 * @property-read int $period_number
 * @property-read Carbon $start_date
 * @property-read Carbon $end_date
 * @property-read AccountingPeriodStatus $status
 * @property-read Carbon|null $closed_at
 * @property-read int|null $closed_by
 * @property-read string|null $closed_reason
 */
class AccountingPeriod extends Model
{
    /** @use HasFactory<AccountingPeriodFactory> */
    use HasFactory, HasUlidIdentifier;

    protected $fillable = [
        'fiscal_year_id',
        'organization_id',
        'name',
        'period_number',
        'start_date',
        'end_date',
        'status',
        'closed_at',
        'closed_by',
        'closed_reason',
        'reopened_at',
        'reopened_by',
        'reopen_reason',
    ];

    protected function casts(): array
    {
        return [
            'period_number' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => AccountingPeriodStatus::class,
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', AccountingPeriodStatus::Open);
    }

    public function isOpen(): bool
    {
        return $this->status === AccountingPeriodStatus::Open;
    }

    /**
     * Check if the given date falls within this period.
     */
    public function containsDate(Carbon $date): bool
    {
        return $date->gte($this->start_date) && $date->lte($this->end_date);
    }
}
