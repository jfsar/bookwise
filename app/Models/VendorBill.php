<?php

namespace App\Models;

use App\Enums\VendorBillStatus;
use App\Models\Concerns\HasUlidIdentifier;
use Carbon\Carbon;
use Database\Factories\VendorBillFactory;
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
 * @property-read string $bill_number
 * @property-read string|null $vendor_reference
 * @property-read Carbon $bill_date
 * @property-read Carbon $due_date
 * @property-read int $currency_id
 * @property-read string $exchange_rate
 * @property-read int $base_currency_id
 * @property-read string $subtotal
 * @property-read string $discount_amount
 * @property-read string $tax_amount
 * @property-read string $total_amount
 * @property-read string $paid_amount
 * @property-read string $balance_due
 * @property-read VendorBillStatus $status
 * @property-read int|null $journal_entry_id
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
 * @property-read JournalEntry|null $journalEntry
 * @property-read User $creator
 */
class VendorBill extends Model
{
    /** @use HasFactory<VendorBillFactory> */
    use HasFactory, HasUlidIdentifier;

    protected $fillable = [
        'organization_id',
        'vendor_id',
        'bill_number',
        'vendor_reference',
        'bill_date',
        'due_date',
        'currency_id',
        'exchange_rate',
        'base_currency_id',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'balance_due',
        'status',
        'journal_entry_id',
        'notes',
        'posted_at',
        'voided_at',
        'voided_by',
        'void_reason',
        'idempotency_key',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'bill_date' => 'date',
            'due_date' => 'date',
            'exchange_rate' => 'decimal:10',
            'subtotal' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'paid_amount' => 'decimal:4',
            'balance_due' => 'decimal:4',
            'status' => VendorBillStatus::class,
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

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(VendorBillLine::class, 'bill_id');
    }

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', VendorBillStatus::Draft);
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', VendorBillStatus::Posted);
    }

    public function isDraft(): bool
    {
        return $this->status === VendorBillStatus::Draft;
    }

    public function isVoided(): bool
    {
        return $this->status === VendorBillStatus::Voided;
    }
}
