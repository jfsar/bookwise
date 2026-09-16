<?php

namespace Database\Factories;

use App\Enums\CustomerInvoiceStatus;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerInvoice>
 */
class CustomerInvoiceFactory extends Factory
{
    protected $model = CustomerInvoice::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 100, 10000);

        return [
            'customer_id' => Customer::factory(),
            'organization_id' => fn (array $attributes) => Customer::find($attributes['customer_id'])?->organization_id ?? Organization::factory(),
            'invoice_number' => strtoupper(fake()->unique()->bothify('INV-??###')),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(fake()->randomElement([0, 15, 30, 45, 60]))->toDateString(),
            'currency_id' => fn (array $attributes) => Organization::find($attributes['organization_id'])?->base_currency_id ?? Currency::factory(),
            'exchange_rate' => 1,
            'base_currency_id' => fn (array $attributes) => Organization::find($attributes['organization_id'])?->base_currency_id ?? Currency::factory(),
            'subtotal' => $amount,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $amount,
            'paid_amount' => 0,
            'balance_due' => $amount,
            'status' => CustomerInvoiceStatus::Draft,
            'journal_entry_id' => null,
            'notes' => fake()->optional()->sentence(),
            'idempotency_key' => null,
            'created_by' => User::factory(),
        ];
    }

    public function posted(): static
    {
        return $this->state(fn () => [
            'status' => CustomerInvoiceStatus::Posted,
            'issued_at' => now(),
            'posted_at' => now(),
        ]);
    }

    public function voided(): static
    {
        return $this->state(fn () => [
            'status' => CustomerInvoiceStatus::Voided,
            'voided_at' => now(),
        ]);
    }
}
