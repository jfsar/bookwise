<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerInvoiceLine>
 */
class CustomerInvoiceLineFactory extends Factory
{
    protected $model = CustomerInvoiceLine::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 10);
        $unitPrice = fake()->randomFloat(2, 10, 500);

        return [
            'invoice_id' => CustomerInvoice::factory(),
            'organization_id' => fn (array $attributes) => CustomerInvoice::find($attributes['invoice_id'])->organization_id,
            'line_number' => 1,
            'description' => fake()->sentence(3),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'line_total' => round($quantity * $unitPrice, 2),
            'revenue_account_id' => fn (array $attributes) => Account::factory()->create([
                'organization_id' => CustomerInvoice::find($attributes['invoice_id'])->organization_id,
            ])->id,
            'tax_code_id' => null,
            'currency_id' => null,
            'applied_tax_rate' => null,
        ];
    }
}
