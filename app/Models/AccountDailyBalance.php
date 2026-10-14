<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Derived, non-authoritative daily movement and closing balance per account
 * (§0.23). Rebuilt from posted journal lines; never a source of accounting
 * truth.
 *
 * @property-read int $id
 * @property-read int $organization_id
 * @property-read int $account_id
 * @property-read Carbon $balance_date
 * @property-read string $debit_total
 * @property-read string $credit_total
 * @property-read string $net_change
 * @property-read string $closing_balance
 */
class AccountDailyBalance extends Model
{
    protected $fillable = [
        'organization_id',
        'account_id',
        'balance_date',
        'debit_total',
        'credit_total',
        'net_change',
        'closing_balance',
    ];

    protected function casts(): array
    {
        return [
            'balance_date' => 'date',
            'debit_total' => 'decimal:4',
            'credit_total' => 'decimal:4',
            'net_change' => 'decimal:4',
            'closing_balance' => 'decimal:4',
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
