<?php

namespace Database\Factories;

use App\Enums\JournalEntryStatus;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    public function definition(): array
    {
        $year = fake()->year();

        return [
            'organization_id' => Organization::factory(),
            'journal_id' => Journal::factory(),
            'fiscal_year_id' => FiscalYear::factory(),
            'accounting_period_id' => AccountingPeriod::factory(),
            'entry_number' => strtoupper(fake()->bothify('??-####-######')),
            'entry_date' => fake()->dateTimeBetween("-{$year} years", 'now'),
            'description' => fake()->sentence(),
            'reference' => fake()->optional()->bothify('REF-####'),
            'currency_id' => Currency::factory(),
            'base_currency_id' => Currency::factory(),
            'exchange_rate' => 1,
            'total_debit' => 0,
            'total_credit' => 0,
            'status' => JournalEntryStatus::Draft,
            'created_by' => User::factory(),
        ];
    }

    public function posted(): static
    {
        return $this->state(fn () => [
            'status' => JournalEntryStatus::Posted,
            'posted_by' => User::factory(),
            'posted_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => JournalEntryStatus::Draft]);
    }
}
