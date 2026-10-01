<?php

namespace App\Actions\RecurringJournal;

use App\Actions\Action;
use App\Models\RecurringJournal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Drives every recurring journal that owes an occurrence on or before a date.
 *
 * Missed occurrences are caught up occurrence by occurrence because every
 * scheduled run is a separate accounting fact, and the per-occurrence cap keeps
 * a long-dormant definition from producing an unbounded batch in one pass.
 */
class RunDueRecurringJournals
{
    use Action;

    public function handle(?Carbon $asOf = null, int $maxOccurrencesPerDefinition = 24): array
    {
        $asOf = ($asOf ?? Carbon::now())->copy()->startOfDay();

        $summary = [
            'definitions' => 0,
            'generated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'failures' => [],
        ];

        RecurringJournal::due($asOf)
            ->orderBy('id')
            ->chunkById(50, function (Collection $chunk) use ($asOf, $maxOccurrencesPerDefinition, &$summary) {
                foreach ($chunk as $definition) {
                    $summary['definitions']++;

                    for ($occurrence = 0; $occurrence < $maxOccurrencesPerDefinition; $occurrence++) {
                        $fresh = RecurringJournal::find($definition->id);

                        if (! $fresh || ! $fresh->isActive() || $fresh->next_run_date->greaterThan($asOf)) {
                            break;
                        }

                        try {
                            $execution = (new GenerateRecurringJournal)->handle($fresh);
                        } catch (\Throwable $e) {
                            $summary['failed']++;
                            $summary['failures'][] = "{$fresh->name}: {$e->getMessage()}";

                            Log::warning('Recurring journal generation failed.', [
                                'recurring_journal_id' => $fresh->id,
                                'organization_id' => $fresh->organization_id,
                                'exception' => $e,
                            ]);

                            // The occurrence stays claimed and failed so the next
                            // scheduled pass retries it instead of skipping it.
                            break;
                        }

                        if ($execution->isCompleted()) {
                            $summary['generated']++;
                        } else {
                            // A live worker owns this occurrence, so the schedule
                            // has not moved yet and looping would only re-claim it.
                            $summary['skipped']++;
                            break;
                        }
                    }
                }
            });

        return $summary;
    }

    public function rules(): array
    {
        return [];
    }
}
