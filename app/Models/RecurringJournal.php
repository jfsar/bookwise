<?php

namespace App\Models;

use App\Enums\RecurringJournalFrequency;
use App\Enums\RecurringJournalStatus;
use App\Models\Concerns\HasUlidIdentifier;
use Carbon\Carbon;
use Database\Factories\RecurringJournalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property-read string $ulid
 * @property-read int $organization_id
 * @property-read int $journal_id
 * @property-read string $name
 * @property-read string|null $description
 * @property-read RecurringJournalFrequency $frequency
 * @property-read Carbon $start_date
 * @property-read Carbon|null $end_date
 * @property-read Carbon $next_run_date
 * @property-read RecurringJournalStatus $status
 * @property-read bool $auto_submit
 * @property-read Carbon|null $last_run_at
 * @property-read string|null $last_run_key
 * @property-read int $run_count
 * @property-read int $created_by
 * @property-read Journal $journal
 * @property-read User $creator
 * @property-read Collection<RecurringJournalLine> $lines
 * @property-read Collection<RecurringJournalExecution> $executions
 */
class RecurringJournal extends Model
{
    /** @use HasFactory<RecurringJournalFactory> */
    use HasFactory, HasUlidIdentifier;

    protected $fillable = [
        'organization_id',
        'journal_id',
        'name',
        'description',
        'frequency',
        'start_date',
        'end_date',
        'next_run_date',
        'status',
        'auto_submit',
        'last_run_at',
        'last_run_key',
        'run_count',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'frequency' => RecurringJournalFrequency::class,
            'status' => RecurringJournalStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'next_run_date' => 'date',
            'auto_submit' => 'boolean',
            'last_run_at' => 'datetime',
            'run_count' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RecurringJournalLine::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(RecurringJournalExecution::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', RecurringJournalStatus::Active);
    }

    /**
     * Definitions that still owe an occurrence on or before the given date.
     */
    public function scopeDue(Builder $query, Carbon $asOf): Builder
    {
        return $query->active()
            ->whereDate('next_run_date', '<=', $asOf)
            ->where(function (Builder $query) use ($asOf) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $asOf);
            });
    }

    public function isActive(): bool
    {
        return $this->status === RecurringJournalStatus::Active;
    }

    public function isPaused(): bool
    {
        return $this->status === RecurringJournalStatus::Paused;
    }

    public function isCompleted(): bool
    {
        return $this->status === RecurringJournalStatus::Completed;
    }

    public function totalDebit(): float
    {
        return (float) $this->lines()->sum('debit');
    }

    public function totalCredit(): float
    {
        return (float) $this->lines()->sum('credit');
    }

    public function isBalanced(): bool
    {
        return abs($this->totalDebit() - $this->totalCredit()) < 0.005;
    }

    public function coversDate(Carbon $date): bool
    {
        $date = $date->copy()->startOfDay();

        if ($date->lessThan($this->start_date)) {
            return false;
        }

        return $this->end_date === null || ! $date->greaterThan($this->end_date);
    }

    public function nextOccurrenceAfter(Carbon $date): Carbon
    {
        return $this->frequency->advance($date->copy()->startOfDay(), $this->start_date);
    }

    /**
     * True when no further occurrence fits inside the effective dates.
     */
    public function scheduleExhausted(Carbon $nextOccurrence): bool
    {
        return $this->end_date !== null && $nextOccurrence->greaterThan($this->end_date);
    }
}
