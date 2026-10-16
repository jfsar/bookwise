<?php

namespace Database\Factories;

use App\Enums\RecurringJournalExecutionStatus;
use App\Models\JournalEntry;
use App\Models\RecurringJournal;
use App\Models\RecurringJournalExecution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringJournalExecution>
 */
class RecurringJournalExecutionFactory extends Factory
{
    protected $model = RecurringJournalExecution::class;

    public function definition(): array
    {
        $scheduledRunDate = today();

        return [
            'recurring_journal_id' => RecurringJournal::factory(),
            'organization_id' => fn (array $attributes) => RecurringJournal::find($attributes['recurring_journal_id'])?->organization_id,
            'scheduled_run_date' => $scheduledRunDate,
            'idempotency_key' => 'rje-'.fake()->uuid(),
            'status' => RecurringJournalExecutionStatus::Pending,
            'attempt_count' => 1,
            'started_at' => now(),
            'completed_at' => null,
            'error_message' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => RecurringJournalExecutionStatus::Completed,
            'journal_entry_id' => JournalEntry::factory(),
            'completed_at' => now(),
        ]);
    }

    public function failed(?string $message = 'Generation failed.'): static
    {
        return $this->state(fn () => [
            'status' => RecurringJournalExecutionStatus::Failed,
            'error_message' => $message,
        ]);
    }
}
