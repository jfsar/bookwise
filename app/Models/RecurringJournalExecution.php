<?php

namespace App\Models;

use App\Enums\RecurringJournalExecutionStatus;
use Carbon\Carbon;
use Database\Factories\RecurringJournalExecutionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $organization_id
 * @property-read int $recurring_journal_id
 * @property-read Carbon $scheduled_run_date
 * @property-read string|null $idempotency_key
 * @property-read RecurringJournalExecutionStatus $status
 * @property-read int|null $journal_entry_id
 * @property-read int $attempt_count
 * @property-read Carbon|null $started_at
 * @property-read Carbon|null $completed_at
 * @property-read string|null $error_message
 */
class RecurringJournalExecution extends Model
{
    /** @use HasFactory<RecurringJournalExecutionFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'recurring_journal_id',
        'scheduled_run_date',
        'idempotency_key',
        'status',
        'journal_entry_id',
        'attempt_count',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_run_date' => 'date',
            'status' => RecurringJournalExecutionStatus::class,
            'attempt_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function recurringJournal(): BelongsTo
    {
        return $this->belongsTo(RecurringJournal::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', RecurringJournalExecutionStatus::Pending);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', RecurringJournalExecutionStatus::Completed);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', RecurringJournalExecutionStatus::Failed);
    }

    public function isPending(): bool
    {
        return $this->status === RecurringJournalExecutionStatus::Pending;
    }

    public function isCompleted(): bool
    {
        return $this->status === RecurringJournalExecutionStatus::Completed;
    }

    public function isFailed(): bool
    {
        return $this->status === RecurringJournalExecutionStatus::Failed;
    }

    /**
     * A pending row left behind by a crashed worker may be retried once it
     * can no longer belong to a live run.
     */
    public function isStale(Carbon $asOf): bool
    {
        return $this->status === RecurringJournalExecutionStatus::Pending
            && $this->started_at !== null
            && $this->started_at->lessThan($asOf->copy()->subHour());
    }
}
