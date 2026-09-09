<?php

namespace App\Models;

use App\Enums\JournalEntryStatus;
use App\Models\Concerns\HasUlidIdentifier;
use Carbon\Carbon;
use Database\Factories\JournalEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property-read string $ulid
 * @property-read int $organization_id
 * @property-read int $journal_id
 * @property-read int $fiscal_year_id
 * @property-read int $accounting_period_id
 * @property-read string $entry_number
 * @property-read Carbon $entry_date
 * @property-read string $description
 * @property-read string|null $reference
 * @property-read int $currency_id
 * @property-read int $base_currency_id
 * @property-read string $exchange_rate
 * @property-read string $total_debit
 * @property-read string $total_credit
 * @property-read JournalEntryStatus $status
 * @property-read int $posting_version
 * @property-read string|null $idempotency_key
 * @property-read string|null $reversal_reason
 * @property-read string|null $rejection_reason
 */
class JournalEntry extends Model
{
    /** @use HasFactory<JournalEntryFactory> */
    use HasFactory, HasUlidIdentifier;

    protected $fillable = [
        'organization_id',
        'journal_id',
        'fiscal_year_id',
        'accounting_period_id',
        'entry_number',
        'entry_date',
        'description',
        'reference',
        'currency_id',
        'base_currency_id',
        'exchange_rate',
        'total_debit',
        'total_credit',
        'status',
        'posting_version',
        'idempotency_key',
        'reversal_reason',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'posted_at',
        'posted_by',
        'reversed_at',
        'reversed_by',
        'reversal_of_id',
        'rejection_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'exchange_rate' => 'decimal:10',
            'total_debit' => 'decimal:4',
            'total_credit' => 'decimal:4',
            'status' => JournalEntryStatus::class,
            'posting_version' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
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

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_of_id');
    }

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', JournalEntryStatus::Draft);
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', JournalEntryStatus::Posted);
    }

    public function scopeForPeriod(Builder $query, AccountingPeriod $period): Builder
    {
        return $query->where('accounting_period_id', $period->id);
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

    public function isDraft(): bool
    {
        return $this->status === JournalEntryStatus::Draft;
    }

    public function isPosted(): bool
    {
        return $this->status === JournalEntryStatus::Posted;
    }
}
