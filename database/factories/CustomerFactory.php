<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'customer_number' => strtoupper(fake()->unique()->bothify('CUST-??###')),
            'name' => fake()->company(),
            'email' => fake()->optional()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'tax_number' => fake()->optional()->numerify('TAX-########'),
            'payment_terms_days' => fake()->randomElement([0, 15, 30, 45, 60]),
            'credit_limit' => fake()->optional(0.8)->randomFloat(2, 1000, 100000),
            'receivable_account_id' => Account::factory(),
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
