<?php

namespace App\Models;

use Database\Factories\JournalEntryLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $organization_id
 * @property-read int $journal_entry_id
 * @property-read int $account_id
 * @property-read int $line_number
 * @property-read string|null $description
 * @property-read string $debit
 * @property-read string $credit
 * @property-read int $currency_id
 * @property-read string|null $currency_amount
 * @property-read string $base_amount
 * @property-read string $exchange_rate
 * @property-read string|null $reference
 */
class JournalEntryLine extends Model
{
    /** @use HasFactory<JournalEntryLineFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'journal_entry_id',
        'account_id',
        'line_number',
        'description',
        'debit',
        'credit',
        'currency_id',
        'currency_amount',
        'base_amount',
        'exchange_rate',
        'reference',
    ];

    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'debit' => 'decimal:4',
            'credit' => 'decimal:4',
            'currency_amount' => 'decimal:4',
            'base_amount' => 'decimal:4',
            'exchange_rate' => 'decimal:10',
        ];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
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
