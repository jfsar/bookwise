<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'vendor_number' => strtoupper(fake()->unique()->bothify('VEND-??###')),
            'name' => fake()->company(),
            'email' => fake()->optional()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'tax_number' => fake()->optional()->numerify('TAX-########'),
            'payment_terms_days' => fake()->randomElement([0, 15, 30, 45, 60]),
            'payable_account_id' => Account::factory(),
            'notes' => fake()->optional()->sentence(),
            'status' => 'active',
            'created_by' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }
}
