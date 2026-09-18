<?php

namespace App\Models;

use Database\Factories\VendorBillLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $organization_id
 * @property-read int $bill_id
 * @property-read int $line_number
 * @property-read string $description
 * @property-read string $quantity
 * @property-read string $unit_price
 * @property-read string $discount_amount
 * @property-read string $tax_amount
 * @property-read string $line_total
 * @property-read int $expense_account_id
 * @property-read int|null $tax_code_id
 * @property-read int|null $currency_id
 * @property-read string|null $applied_tax_rate
 * @property-read VendorBill $bill
 * @property-read Account $expenseAccount
 * @property-read TaxCode|null $taxCode
 * @property-read Currency|null $currency
 */
class VendorBillLine extends Model
{
    /** @use HasFactory<VendorBillLineFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'bill_id',
        'line_number',
        'description',
        'quantity',
        'unit_price',
        'discount_amount',
        'tax_amount',
        'line_total',
        'expense_account_id',
        'tax_code_id',
        'currency_id',
        'applied_tax_rate',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
            'applied_tax_rate' => 'decimal:6',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class, 'bill_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class, 'tax_code_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }
}
