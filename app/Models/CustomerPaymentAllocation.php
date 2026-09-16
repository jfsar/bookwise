<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $organization_id
 * @property-read int $payment_id
 * @property-read int $invoice_id
 * @property-read string $amount
 * @property-read Carbon $allocated_at
 * @property-read int $created_by
 * @property-read CustomerPayment $payment
 * @property-read CustomerInvoice $invoice
 */
class CustomerPaymentAllocation extends Model
{
    protected $fillable = [
        'organization_id',
        'payment_id',
        'invoice_id',
        'amount',
        'allocated_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'allocated_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class, 'payment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class, 'invoice_id');
    }
}
