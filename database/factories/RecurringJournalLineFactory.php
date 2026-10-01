<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\RecurringJournal;
use App\Models\RecurringJournalLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringJournalLine>
 */
class RecurringJournalLineFactory extends Factory
{
    protected $model = RecurringJournalLine::class;

    public function definition(): array
    {
        return [
            'recurring_journal_id' => RecurringJournal::factory(),
            'organization_id' => fn (array $attributes) => RecurringJournal::find($attributes['recurring_journal_id'])?->organization_id,
            'account_id' => fn (array $attributes) => Account::factory()->create([
                'organization_id' => RecurringJournal::find($attributes['recurring_journal_id'])?->organization_id,
            ])->id,
            'line_number' => 1,
            'description' => fake()->sentence(),
            'debit' => 0,
            'credit' => 0,
        ];
    }

    public function debit(float $amount = 1000): static
    {
        return $this->state(fn () => ['debit' => $amount, 'credit' => 0]);
    }

    public function credit(float $amount = 1000): static
    {
        return $this->state(fn () => ['debit' => 0, 'credit' => $amount]);
    }
}
