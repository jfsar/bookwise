<?php

namespace App\Console\Commands;

use App\Actions\RecurringJournal\RunDueRecurringJournals;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateRecurringJournals extends Command
{
    /**
     * @var string
     */
    protected $signature = 'recurring-journals:generate
                            {--date= : Treat this date as the current run date (defaults to today)}
                            {--max-occurrences=24 : Maximum missed occurrences generated per definition in one pass}';

    /**
     * @var string
     */
    protected $description = 'Generate draft journal entries for every recurring journal that is due';

    public function handle(RunDueRecurringJournals $runDueRecurringJournals): int
    {
        $asOf = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::now()->startOfDay();

        $summary = $runDueRecurringJournals->handle($asOf, max(1, (int) $this->option('max-occurrences')));

        $this->info(sprintf(
            'Checked %d recurring journal(s): %d generated, %d skipped, %d failed.',
            $summary['definitions'],
            $summary['generated'],
            $summary['skipped'],
            $summary['failed'],
        ));

        foreach ($summary['failures'] as $failure) {
            $this->warn($failure);
        }

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
