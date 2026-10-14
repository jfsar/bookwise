<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Derived, non-authoritative line-level reporting fact (§0.23). One row per
 * posted journal entry line. Rebuilt from posted journal lines; never a source
 * of accounting truth.
 *
 * @property-read int $id
 * @property-read int $organization_id
 * @property-read int $journal_entry_id
 * @property-read int $journal_entry_line_id
 * @property-read int $account_id
 * @property-read int|null $journal_id
 * @property-read int|null $fiscal_year_id
 * @property-read int|null $accounting_period_id
 * @property-read Carbon $entry_date
 * @property-read string $entry_number
 * @property-read string $account_code
 * @property-read string $debit_amount
 * @property-read string $credit_amount
 * @property-read string $base_amount
 */
class ReportingFactTable extends Model
{
    protected $fillable = [
        'organization_id',
        'journal_entry_id',
        'journal_entry_line_id',
        'account_id',
        'journal_id',
        'fiscal_year_id',
        'accounting_period_id',
        'entry_date',
        'entry_number',
        'account_code',
        'debit_amount',
        'credit_amount',
        'base_amount',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'debit_amount' => 'decimal:4',
            'credit_amount' => 'decimal:4',
            'base_amount' => 'decimal:4',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function journalEntryLine(): BelongsTo
    {
        return $this->belongsTo(JournalEntryLine::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class);
    }
}
