<?php

namespace App\Models;

use Database\Factories\BankReconciliationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $organization_id
 * @property-read int $bank_account_id
 * @property-read int $currency_id
 * @property-read string $statement_date
 * @property-read string $statement_start_date
 * @property-read string $statement_end_date
 * @property-read string $opening_balance
 * @property-read string $closing_balance
 * @property-read string $reconciled_balance
 * @property-read string $difference
 * @property-read string $status
 * @property-read int $prepared_by
 * @property-read int|null $approved_by
 * @property-read string|null $finalization_reason
 * @property-read Carbon|null $reconciled_at
 * @property-read int|null $reconciled_by
 * @property-read Organization $organization
 * @property-read BankAccount $bankAccount
 * @property-read Currency $currency
 * @property-read User $preparer
 * @property-read User|null $approver
 * @property-read User|null $reconciler
 * @property-read Collection<int, ReconciliationMatch> $matches
 */
class BankReconciliation extends Model
{
    /** @use HasFactory<BankReconciliationFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'bank_account_id',
        'currency_id',
        'statement_date',
        'statement_start_date',
        'statement_end_date',
        'opening_balance',
        'closing_balance',
        'reconciled_balance',
        'difference',
        'status',
        'prepared_by',
        'approved_by',
        'finalization_reason',
        'reconciled_at',
        'reconciled_by',
    ];

    protected function casts(): array
    {
        return [
            'statement_date' => 'date:Y-m-d',
            'statement_start_date' => 'date:Y-m-d',
            'statement_end_date' => 'date:Y-m-d',
            'opening_balance' => 'decimal:4',
            'closing_balance' => 'decimal:4',
            'reconciled_balance' => 'decimal:4',
            'difference' => 'decimal:4',
            'reconciled_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(ReconciliationMatch::class, 'reconciliation_id');
    }

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }

    /**
     * A reconciliation is open while it can still be matched against.
     * Finalized reconciliations are immutable (§0.13 / §7.34).
     */
    public function isOpen(): bool
    {
        return $this->status !== 'finalized';
    }

    public function isFinalized(): bool
    {
        return $this->status === 'finalized';
    }

    public function isBalanced(): bool
    {
        return bccomp((string) $this->difference, '0', 4) === 0;
    }

    /**
     * Whether a bank or ledger date falls outside the statement period.
     *
     * Timing differences — outstanding cheques, deposits in transit — are the
     * whole point of a reconciliation, so such a row may still be matched. It
     * is only recorded (audit trail) and surfaced on the statement, never
     * rejected. ISO dates compare correctly as strings.
     */
    public function isOutsideStatementPeriod(?Carbon $date): bool
    {
        if ($date === null) {
            return false;
        }

        $iso = $date->format('Y-m-d');

        return $iso < $this->statement_start_date->format('Y-m-d')
            || $iso > $this->statement_end_date->format('Y-m-d');
    }
}
