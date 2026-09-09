<?php

namespace Database\Factories;

use App\Models\AccountType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountType>
 */
class AccountTypeFactory extends Factory
{
    protected $model = AccountType::class;

    public function definition(): array
    {
        return [
            'code' => strtolower(fake()->unique()->bothify('??')),
            'name' => fake()->word(),
            'category' => fake()->randomElement(['asset', 'liability', 'equity', 'revenue', 'expense']),
            'normal_balance' => fake()->randomElement(['debit', 'credit']),
            'description' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }
}
