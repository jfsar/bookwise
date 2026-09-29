<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\Organization;
use App\Models\User;
use App\Services\BankAccountNumberService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        $accountNumber = fake()->numerify('##########');

        return [
            'account_id' => Account::factory(),
            'organization_id' => fn (array $attributes) => Account::find($attributes['account_id'])?->organization_id ?? Organization::factory(),
            'currency_id' => fn (array $attributes) => Organization::find($attributes['organization_id'])?->base_currency_id ?? Currency::factory(),
            'bank_name' => fake()->company().' Bank',
            'account_name' => fake()->word(),
            'iban' => fake()->optional()->iban(),
            'routing_number' => fake()->optional()->numerify('#########'),
            'masked_account_number' => app(BankAccountNumberService::class)->mask($accountNumber),
            'encrypted_account_number' => $accountNumber,
            'last4' => substr($accountNumber, -4),
            'opening_balance' => 0,
            'status' => 'active',
            'created_by' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => 'closed']);
    }
}
