<?php

namespace App\Models;

use Database\Factories\ReconciliationMatchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single many-to-many link between a bank transaction and a posted journal
 * entry line within a reconciliation (§0.13). One bank transaction may match
 * many journal lines, and many bank transactions may match one journal line.
 *
 * @property-read int $id
 * @property-read int|null $organization_id
 * @property-read int $reconciliation_id
 * @property-read int $bank_transaction_id
 * @property-read int|null $journal_entry_line_id
 * @property-read string $matched_amount
 * @property-read string $match_type
 * @property-read int $created_by
 * @property-read Organization $organization
 * @property-read BankReconciliation $reconciliation
 * @property-read BankTransaction $bankTransaction
 * @property-read JournalEntryLine|null $journalEntryLine
 * @property-read User $creator
 */
class ReconciliationMatch extends Model
{
    /** @use HasFactory<ReconciliationMatchFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'reconciliation_id',
        'bank_transaction_id',
        'journal_entry_line_id',
        'matched_amount',
        'match_type',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'matched_amount' => 'decimal:4',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'reconciliation_id');
    }

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }

    public function journalEntryLine(): BelongsTo
    {
        return $this->belongsTo(JournalEntryLine::class, 'journal_entry_line_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }
}
