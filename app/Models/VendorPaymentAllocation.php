<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $organization_id
 * @property-read int $payment_id
 * @property-read int $bill_id
 * @property-read string $amount
 * @property-read Carbon $allocated_at
 * @property-read int $created_by
 * @property-read VendorPayment $payment
 * @property-read VendorBill $bill
 * @property-read User $creator
 */
class VendorPaymentAllocation extends Model
{
    protected $fillable = [
        'organization_id',
        'payment_id',
        'bill_id',
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
        return $this->belongsTo(VendorPayment::class, 'payment_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class, 'bill_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }
}
