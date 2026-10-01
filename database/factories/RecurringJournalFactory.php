<?php

namespace Database\Factories;

use App\Enums\RecurringJournalFrequency;
use App\Enums\RecurringJournalStatus;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\RecurringJournal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringJournal>
 */
class RecurringJournalFactory extends Factory
{
    protected $model = RecurringJournal::class;

    public function definition(): array
    {
        $startDate = today();

        return [
            'journal_id' => Journal::factory(),
            'organization_id' => fn (array $attributes) => Journal::find($attributes['journal_id'])?->organization_id ?? Organization::factory(),
            'name' => ucfirst(fake()->unique()->words(3, true)),
            'description' => fake()->sentence(),
            'frequency' => RecurringJournalFrequency::Monthly,
            'start_date' => $startDate,
            'end_date' => null,
            'next_run_date' => $startDate,
            'status' => RecurringJournalStatus::Active,
            'auto_submit' => false,
            'created_by' => User::factory(),
        ];
    }

    public function paused(): static
    {
        return $this->state(fn () => ['status' => RecurringJournalStatus::Paused]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => RecurringJournalStatus::Completed]);
    }

    public function monthly(): static
    {
        return $this->state(fn () => ['frequency' => RecurringJournalFrequency::Monthly]);
    }

    public function autoSubmitting(): static
    {
        return $this->state(fn () => ['auto_submit' => true]);
    }
}
