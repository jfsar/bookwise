<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Derived, non-authoritative cumulative balance per account as of a posted
 * activity date (§0.23). Rebuilt from posted journal lines; never a source of
 * accounting truth.
 *
 * @property-read int $id
 * @property-read int $organization_id
 * @property-read int $account_id
 * @property-read Carbon $as_of_date
 * @property-read string $debit_balance
 * @property-read string $credit_balance
 * @property-read string $net_balance
 */
class LedgerBalanceSnapshot extends Model
{
    protected $fillable = [
        'organization_id',
        'account_id',
        'as_of_date',
        'debit_balance',
        'credit_balance',
        'net_balance',
    ];

    protected function casts(): array
    {
        return [
            'as_of_date' => 'date',
            'debit_balance' => 'decimal:4',
            'credit_balance' => 'decimal:4',
            'net_balance' => 'decimal:4',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
