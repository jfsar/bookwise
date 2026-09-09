<?php

namespace Database\Factories;

use App\Enums\NormalBalance;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'account_type_id' => AccountType::factory(),
            'parent_id' => null,
            'code' => strtoupper(fake()->unique()->bothify('??###')),
            'name' => fake()->word(),
            'description' => fake()->sentence(),
            'normal_balance' => NormalBalance::Debit,
            'is_posting_account' => true,
            'is_system_account' => false,
            'is_active' => true,
            'allow_manual_posting' => true,
            'created_by' => User::factory(),
        ];
    }

    public function posting(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_posting_account' => true,
        ]);
    }

    public function nonPosting(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_posting_account' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
