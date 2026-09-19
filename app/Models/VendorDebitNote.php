<?php

namespace App\Models;

use App\Enums\VendorDebitNoteStatus;
use App\Models\Concerns\HasUlidIdentifier;
use Carbon\Carbon;
use Database\Factories\VendorDebitNoteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read string $ulid
 * @property-read int $organization_id
 * @property-read int $vendor_id
 * @property-read int $bill_id
 * @property-read string $debit_note_number
 * @property-read Carbon $date
 * @property-read int $currency_id
 * @property-read string $exchange_rate
 * @property-read string $subtotal
 * @property-read string $tax_amount
 * @property-read string $total_amount
 * @property-read string $reason
 * @property-read int|null $journal_entry_id
 * @property-read VendorDebitNoteStatus $status
 * @property-read Carbon|null $posted_at
 * @property-read Carbon|null $voided_at
 * @property-read int|null $voided_by
 * @property-read string|null $void_reason
 * @property-read string|null $idempotency_key
 * @property-read int $created_by
 * @property-read Organization $organization
 * @property-read Vendor $vendor
 * @property-read VendorBill $bill
 * @property-read Currency $currency
 * @property-read JournalEntry|null $journalEntry
 * @property-read User $creator
 * @property-read User|null $voider
 */
class VendorDebitNote extends Model
{
    /** @use HasFactory<VendorDebitNoteFactory> */
    use HasFactory, HasUlidIdentifier;

    protected $fillable = [
        'organization_id',
        'vendor_id',
        'bill_id',
        'debit_note_number',
        'date',
        'currency_id',
        'exchange_rate',
        'subtotal',
        'tax_amount',
        'total_amount',
        'reason',
        'journal_entry_id',
        'status',
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
            'date' => 'date',
            'exchange_rate' => 'decimal:10',
            'subtotal' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'status' => VendorDebitNoteStatus::class,
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

    public function bill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class, 'bill_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
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

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', VendorDebitNoteStatus::Draft);
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', VendorDebitNoteStatus::Posted);
    }

    public function isDraft(): bool
    {
        return $this->status === VendorDebitNoteStatus::Draft;
    }

    public function isPosted(): bool
    {
        return $this->status === VendorDebitNoteStatus::Posted;
    }

    public function isVoided(): bool
    {
        return $this->status === VendorDebitNoteStatus::Voided;
    }
}
