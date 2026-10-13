<?php

namespace App\Models;

use App\Models\Concerns\HasUlidIdentifier;
use Database\Factories\BankAccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read string $ulid
 * @property-read int $organization_id
 * @property-read int $account_id
 * @property-read int $currency_id
 * @property-read string $bank_name
 * @property-read string $account_name
 * @property-read string|null $iban
 * @property-read string|null $routing_number
 * @property-read string|null $masked_account_number
 * @property-read string|null $encrypted_account_number
 * @property-read string|null $last4
 * @property-read string $opening_balance
 * @property-read string $status
 * @property-read int $created_by
 * @property-read int|null $updated_by
 * @property-read Organization $organization
 * @property-read Account $account
 * @property-read Currency $currency
 * @property-read User $creator
 */
class BankAccount extends Model
{
    /** @use HasFactory<BankAccountFactory> */
    use HasFactory, HasUlidIdentifier;

    protected $fillable = [
        'organization_id',
        'account_id',
        'currency_id',
        'bank_name',
        'account_name',
        'iban',
        'routing_number',
        'masked_account_number',
        'encrypted_account_number',
        'last4',
        'opening_balance',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:4',
            'encrypted_account_number' => 'encrypted',
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

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
