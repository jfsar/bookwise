<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankTransaction>
 */
class BankTransactionFactory extends Factory
{
    protected $model = BankTransaction::class;

    public function definition(): array
    {
        return [
            'organization_id' => fn (array $attributes) => BankAccount::find($attributes['bank_account_id'])?->organization_id,
            'bank_account_id' => BankAccount::factory(),
            'transaction_date' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'value_date' => null,
            'reference' => null,
            'description' => fake()->sentence(),
            'debit' => fake()->randomFloat(2, 100, 10000),
            'credit' => 0,
            'currency_id' => fn (array $attributes) => BankAccount::find($attributes['bank_account_id'])?->currency_id,
            'source' => 'manual',
            'reconciliation_status' => 'unreconciled',
        ];
    }

    public function debit(?float $amount = null): static
    {
        return $this->state(fn () => [
            'debit' => $amount ?? fake()->randomFloat(2, 100, 10000),
            'credit' => 0,
        ]);
    }

    public function credit(?float $amount = null): static
    {
        return $this->state(fn () => [
            'debit' => 0,
            'credit' => $amount ?? fake()->randomFloat(2, 100, 10000),
        ]);
    }

    public function imported(): static
    {
        return $this->state([
            'source' => 'import',
            'reconciliation_status' => 'unreconciled',
        ]);
    }

    public function reconciled(): static
    {
        return $this->state(['reconciliation_status' => 'reconciled']);
    }
}
