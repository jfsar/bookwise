<?php

namespace App\Models;

use Database\Factories\BankTransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $organization_id
 * @property-read int $bank_account_id
 * @property-read string $transaction_date
 * @property-read string|null $value_date
 * @property-read string|null $reference
 * @property-read string|null $description
 * @property-read string $debit
 * @property-read string $credit
 * @property-read string|null $balance
 * @property-read string|null $external_reference
 * @property-read string|null $external_transaction_id
 * @property-read int|null $import_batch_id
 * @property-read string|null $transaction_hash
 * @property-read int $currency_id
 * @property-read string $source
 * @property-read string $reconciliation_status
 * @property-read BankAccount $bankAccount
 * @property-read Currency $currency
 * @property-read BankImportBatch|null $importBatch
 */
class BankTransaction extends Model
{
    /** @use HasFactory<BankTransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'bank_account_id',
        'transaction_date',
        'value_date',
        'reference',
        'description',
        'debit',
        'credit',
        'balance',
        'external_reference',
        'external_transaction_id',
        'import_batch_id',
        'transaction_hash',
        'currency_id',
        'source',
        'reconciliation_status',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date:Y-m-d',
            'value_date' => 'date:Y-m-d',
            'debit' => 'decimal:4',
            'credit' => 'decimal:4',
            'balance' => 'decimal:4',
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

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(BankImportBatch::class, 'import_batch_id');
    }

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }

    public function scopeForBankAccount(Builder $query, int $bankAccountId): Builder
    {
        return $query->where('bank_account_id', $bankAccountId);
    }

    public function isManual(): bool
    {
        return $this->source === 'manual';
    }

    public function isReconciled(): bool
    {
        return $this->reconciliation_status === 'reconciled';
    }

    public function isEditable(): bool
    {
        return $this->isManual() && $this->reconciliation_status === 'unreconciled';
    }
}
