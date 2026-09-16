<?php

namespace Database\Factories;

use App\Enums\CustomerPaymentStatus;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerPayment>
 */
class CustomerPaymentFactory extends Factory
{
    protected $model = CustomerPayment::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'organization_id' => fn (array $attributes) => Customer::find($attributes['customer_id'])?->organization_id ?? Organization::factory(),
            'payment_number' => strtoupper(fake()->unique()->bothify('PAY-??###')),
            'payment_date' => now()->toDateString(),
            'currency_id' => fn (array $attributes) => Organization::find($attributes['organization_id'])?->base_currency_id ?? Currency::factory(),
            'exchange_rate' => 1,
            'base_currency_id' => fn (array $attributes) => Organization::find($attributes['organization_id'])?->base_currency_id ?? Currency::factory(),
            'amount' => fake()->randomFloat(2, 100, 10000),
            'payment_method' => fake()->randomElement(['cash', 'bank_transfer', 'check', 'credit_card', 'other']),
            'bank_account_id' => null,
            'cash_account_id' => null,
            'reference' => fake()->optional()->numerify('REF-####'),
            'journal_entry_id' => null,
            'status' => CustomerPaymentStatus::Draft,
            'notes' => fake()->optional()->sentence(),
            'posted_at' => null,
            'idempotency_key' => null,
            'created_by' => User::factory(),
        ];
    }

    public function posted(): static
    {
        return $this->state(fn () => [
            'status' => CustomerPaymentStatus::Posted,
            'posted_at' => now(),
        ]);
    }
}
