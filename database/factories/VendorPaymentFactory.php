<?php

namespace Database\Factories;

use App\Enums\VendorPaymentStatus;
use App\Models\Currency;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorPayment>
 */
class VendorPaymentFactory extends Factory
{
    protected $model = VendorPayment::class;

    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'organization_id' => fn (array $attributes) => Vendor::find($attributes['vendor_id'])?->organization_id ?? Organization::factory(),
            'payment_number' => strtoupper(fake()->unique()->bothify('VPAY-??###')),
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
            'status' => VendorPaymentStatus::Draft,
            'notes' => fake()->optional()->sentence(),
            'posted_at' => null,
            'idempotency_key' => null,
            'created_by' => User::factory(),
        ];
    }

    public function posted(): static
    {
        return $this->state(fn () => [
            'status' => VendorPaymentStatus::Posted,
            'posted_at' => now(),
        ]);
    }
}
