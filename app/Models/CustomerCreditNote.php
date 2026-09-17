<?php

namespace App\Models;

use App\Enums\CustomerCreditNoteStatus;
use App\Models\Concerns\HasUlidIdentifier;
use Carbon\Carbon;
use Database\Factories\CustomerCreditNoteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read string $ulid
 * @property-read int $organization_id
 * @property-read int $customer_id
 * @property-read int $invoice_id
 * @property-read string $credit_note_number
 * @property-read Carbon $date
 * @property-read int $currency_id
 * @property-read string $exchange_rate
 * @property-read string $subtotal
 * @property-read string $tax_amount
 * @property-read string $total_amount
 * @property-read string $reason
 * @property-read int|null $journal_entry_id
 * @property-read CustomerCreditNoteStatus $status
 * @property-read Carbon|null $posted_at
 * @property-read string|null $idempotency_key
 * @property-read int $created_by
 * @property-read Organization $organization
 * @property-read Customer $customer
 * @property-read CustomerInvoice $invoice
 * @property-read Currency $currency
 * @property-read JournalEntry|null $journalEntry
 * @property-read User $creator
 */
class CustomerCreditNote extends Model
{
    /** @use HasFactory<CustomerCreditNoteFactory> */
    use HasFactory, HasUlidIdentifier;

    protected $fillable = [
        'organization_id',
        'customer_id',
        'invoice_id',
        'credit_note_number',
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
            'status' => CustomerCreditNoteStatus::class,
            'posted_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class, 'invoice_id');
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

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', CustomerCreditNoteStatus::Draft);
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', CustomerCreditNoteStatus::Posted);
    }

    public function isDraft(): bool
    {
        return $this->status === CustomerCreditNoteStatus::Draft;
    }

    public function isPosted(): bool
    {
        return $this->status === CustomerCreditNoteStatus::Posted;
    }

    public function isVoided(): bool
    {
        return $this->status === CustomerCreditNoteStatus::Voided;
    }
}
