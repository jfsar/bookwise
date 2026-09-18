<?php

namespace Database\Factories;

use App\Enums\VendorBillStatus;
use App\Models\Currency;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorBill>
 */
class VendorBillFactory extends Factory
{
    protected $model = VendorBill::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 100, 10000);

        return [
            'vendor_id' => Vendor::factory(),
            'organization_id' => fn (array $attributes) => Vendor::find($attributes['vendor_id'])?->organization_id ?? Organization::factory(),
            'bill_number' => strtoupper(fake()->unique()->bothify('BILL-??###')),
            'vendor_reference' => fake()->optional()->bothify('RR-####'),
            'bill_date' => now()->toDateString(),
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
            'status' => VendorBillStatus::Draft,
            'journal_entry_id' => null,
            'notes' => fake()->optional()->sentence(),
            'idempotency_key' => null,
            'created_by' => User::factory(),
        ];
    }

    public function posted(): static
    {
        return $this->state(fn () => [
            'status' => VendorBillStatus::Posted,
            'posted_at' => now(),
        ]);
    }

    public function voided(): static
    {
        return $this->state(fn () => [
            'status' => VendorBillStatus::Voided,
            'voided_at' => now(),
        ]);
    }
}
