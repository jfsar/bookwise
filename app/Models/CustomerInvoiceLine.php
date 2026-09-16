<?php

namespace App\Models;

use Database\Factories\CustomerInvoiceLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $organization_id
 * @property-read int $invoice_id
 * @property-read int $line_number
 * @property-read string $description
 * @property-read string $quantity
 * @property-read string $unit_price
 * @property-read string $discount_amount
 * @property-read string $tax_amount
 * @property-read string $line_total
 * @property-read int $revenue_account_id
 * @property-read int|null $tax_code_id
 * @property-read int|null $currency_id
 * @property-read string|null $applied_tax_rate
 * @property-read CustomerInvoice $invoice
 * @property-read Account $revenueAccount
 * @property-read TaxCode|null $taxCode
 */
class CustomerInvoiceLine extends Model
{
    /** @use HasFactory<CustomerInvoiceLineFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'invoice_id',
        'line_number',
        'description',
        'quantity',
        'unit_price',
        'discount_amount',
        'tax_amount',
        'line_total',
        'revenue_account_id',
        'tax_code_id',
        'currency_id',
        'applied_tax_rate',
    ];

    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
            'applied_tax_rate' => 'decimal:6',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class, 'invoice_id');
    }

    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'revenue_account_id');
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class, 'tax_code_id');
    }
}
