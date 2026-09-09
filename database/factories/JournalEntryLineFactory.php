<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntryLine>
 */
class JournalEntryLineFactory extends Factory
{
    protected $model = JournalEntryLine::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 100, 100000);

        return [
            'organization_id' => Organization::factory(),
            'journal_entry_id' => JournalEntry::factory(),
            'account_id' => Account::factory(),
            'line_number' => fake()->numberBetween(1, 10),
            'description' => fake()->optional()->sentence(),
            'debit' => $amount,
            'credit' => 0,
            'currency_id' => Currency::factory(),
            'currency_amount' => $amount,
            'base_amount' => $amount,
            'exchange_rate' => 1,
            'reference' => fake()->optional()->bothify('REF-####'),
        ];
    }

    public function debit(): static
    {
        return $this->state(fn () => ['debit' => fake()->randomFloat(2, 100, 100000), 'credit' => 0]);
    }

    public function credit(): static
    {
        return $this->state(fn () => ['credit' => fake()->randomFloat(2, 100, 100000), 'debit' => 0]);
    }
}
