<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankReconciliation>
 */
class BankReconciliationFactory extends Factory
{
    protected $model = BankReconciliation::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-3 months', '-1 month')->format('Y-m-d');
        $end = fake()->dateTimeBetween($start, '+1 month')->format('Y-m-d');

        return [
            'organization_id' => fn (array $attributes) => BankAccount::find($attributes['bank_account_id'])?->organization_id,
            'bank_account_id' => BankAccount::factory(),
            'currency_id' => fn (array $attributes) => BankAccount::find($attributes['bank_account_id'])?->currency_id,
            'statement_date' => $end,
            'statement_start_date' => $start,
            'statement_end_date' => $end,
            'opening_balance' => 0,
            'closing_balance' => 0,
            'reconciled_balance' => 0,
            'difference' => 0,
            'status' => 'in_progress',
            'prepared_by' => User::factory(),
        ];
    }

    public function finalized(): static
    {
        return $this->state(fn () => [
            'status' => 'finalized',
            'approved_by' => User::factory()->create()->id,
            'reconciled_at' => now(),
            'reconciled_by' => User::factory()->create()->id,
        ]);
    }

    public function reopened(): static
    {
        return $this->state(['status' => 'reopened']);
    }
}
