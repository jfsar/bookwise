<?php

namespace App\Models;

use App\Enums\VendorPaymentStatus;
use App\Models\Concerns\HasUlidIdentifier;
use Carbon\Carbon;
use Database\Factories\VendorPaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property-read string $ulid
 * @property-read int $organization_id
 * @property-read int $vendor_id
 * @property-read string $payment_number
 * @property-read Carbon $payment_date
 * @property-read int $currency_id
 * @property-read string $exchange_rate
 * @property-read int $base_currency_id
 * @property-read string $amount
 * @property-read string $payment_method
 * @property-read int|null $bank_account_id
 * @property-read int|null $cash_account_id
 * @property-read string|null $reference
 * @property-read int|null $journal_entry_id
 * @property-read VendorPaymentStatus $status
 * @property-read string|null $notes
 * @property-read Carbon|null $posted_at
 * @property-read Carbon|null $voided_at
 * @property-read int|null $voided_by
 * @property-read string|null $void_reason
 * @property-read string|null $idempotency_key
 * @property-read int $created_by
 * @property-read Organization $organization
 * @property-read Vendor $vendor
 * @property-read Currency $currency
 * @property-read Currency $baseCurrency
 * @property-read Account|null $cashAccount
 * @property-read JournalEntry|null $journalEntry
 * @property-read User $creator
 * @property-read User|null $voider
 */
class VendorPayment extends Model
{
    /** @use HasFactory<VendorPaymentFactory> */
    use HasFactory, HasUlidIdentifier;

    protected $fillable = [
        'organization_id',
        'vendor_id',
        'payment_number',
        'payment_date',
        'currency_id',
        'exchange_rate',
        'base_currency_id',
        'amount',
        'payment_method',
        'bank_account_id',
        'cash_account_id',
        'reference',
        'journal_entry_id',
        'status',
        'notes',
        'posted_at',
        'voided_at',
        'voided_by',
        'void_reason',
        'idempotency_key',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'exchange_rate' => 'decimal:10',
            'amount' => 'decimal:4',
            'status' => VendorPaymentStatus::class,
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cash_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(VendorPaymentAllocation::class, 'payment_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function totalAllocated(): float
    {
        $total = $this->relationLoaded('allocations')
            ? $this->allocations->sum('amount')
            : $this->allocations()->sum('amount');

        return round((float) $total, 4);
    }

    public function remainingAllocatable(): float
    {
        return round((float) $this->amount - $this->totalAllocated(), 4);
    }

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', VendorPaymentStatus::Draft);
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', VendorPaymentStatus::Posted);
    }

    public function isDraft(): bool
    {
        return $this->status === VendorPaymentStatus::Draft;
    }

    public function isPosted(): bool
    {
        return $this->status === VendorPaymentStatus::Posted;
    }
}
