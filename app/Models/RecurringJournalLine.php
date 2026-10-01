<?php

namespace App\Models;

use Database\Factories\RecurringJournalLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $organization_id
 * @property-read int $recurring_journal_id
 * @property-read int $account_id
 * @property-read int $line_number
 * @property-read string|null $description
 * @property-read string $debit
 * @property-read string $credit
 */
class RecurringJournalLine extends Model
{
    /** @use HasFactory<RecurringJournalLineFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'recurring_journal_id',
        'account_id',
        'line_number',
        'description',
        'debit',
        'credit',
    ];

    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'debit' => 'decimal:4',
            'credit' => 'decimal:4',
        ];
    }

    public function recurringJournal(): BelongsTo
    {
        return $this->belongsTo(RecurringJournal::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function isDebit(): bool
    {
        return (float) $this->debit > 0;
    }

    public function isCredit(): bool
    {
        return (float) $this->credit > 0;
    }
}
