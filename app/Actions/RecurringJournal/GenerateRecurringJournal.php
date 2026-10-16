<?php

namespace App\Actions\RecurringJournal;

use App\Actions\Action;
use App\Actions\Concerns\PostsJournalEntries;
use App\Actions\JournalEntry\CreateJournalEntry;
use App\Actions\JournalEntry\SubmitJournalEntry;
use App\Enums\RecurringJournalExecutionStatus;
use App\Enums\RecurringJournalStatus;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Organization;
use App\Models\RecurringJournal;
use App\Models\RecurringJournalExecution;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates the draft journal entry for a single scheduled occurrence.
 *
 * Generation is idempotent per (organization, recurring journal, scheduled run
 * date): the unique execution claim plus the journal entry idempotency key
 * guarantee that parallel workers and scheduler retries never produce two
 * accounting facts for one occurrence. A generated entry is always a draft and
 * is only submitted — never approved or posted — when the definition opts in,
 * so recurring entries keep the approval requirements of the journal workflow.
 */
class GenerateRecurringJournal
{
    use Action, PostsJournalEntries;

    public function handle(RecurringJournal $recurringJournal, ?Carbon $scheduledRunDate = null): RecurringJournalExecution
    {
        $claim = $this->claim($recurringJournal, $scheduledRunDate);

        if (! $claim['owns_attempt']) {
            return $claim['execution'];
        }

        try {
            return $this->generate($claim['recurring_journal'], $claim['execution']);
        } catch (\Throwable $e) {
            $this->markFailed($claim['execution'], $e);

            throw $e;
        }
    }

    /**
     * Reserve the occurrence so concurrent workers cannot generate it twice.
     *
     * @return array{recurring_journal: RecurringJournal, execution: RecurringJournalExecution, owns_attempt: bool}
     */
    private function claim(RecurringJournal $recurringJournal, ?Carbon $scheduledRunDate): array
    {
        return DB::transaction(function () use ($recurringJournal, $scheduledRunDate) {
            $locked = RecurringJournal::where('id', $recurringJournal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isActive()) {
                throw new \RuntimeException('Only an active recurring journal can generate entries.');
            }

            $runDate = ($scheduledRunDate ?? $locked->next_run_date->copy())->startOfDay();

            if (! $locked->coversDate($runDate)) {
                throw new \RuntimeException('The scheduled date falls outside the recurring journal date range.');
            }

            $existing = $this->findExecution($locked, $runDate);

            if ($existing) {
                $retryable = $this->retryable($existing);

                return [
                    'recurring_journal' => $locked,
                    'execution' => $retryable ? $this->takeOverAttempt($existing) : $existing,
                    'owns_attempt' => $retryable,
                ];
            }

            try {
                $execution = RecurringJournalExecution::create([
                    'organization_id' => $locked->organization_id,
                    'recurring_journal_id' => $locked->id,
                    'scheduled_run_date' => $runDate,
                    'idempotency_key' => $this->idempotencyKey($locked, $runDate),
                    'status' => RecurringJournalExecutionStatus::Pending,
                    'attempt_count' => 1,
                    'started_at' => now(),
                ]);

                return [
                    'recurring_journal' => $locked,
                    'execution' => $execution,
                    'owns_attempt' => true,
                ];
            } catch (QueryException $e) {
                if ($e->getCode() !== '23505' || ! str_contains($e->getMessage(), 'uq_rje_org_recurring_run_date')) {
                    throw $e;
                }

                // The definition row stays locked for the whole transaction, so a
                // worker that already owns this occurrence cannot reach the
                // insert. Reaching the unique violation means the row lock was
                // bypassed: abort instead of querying an aborted transaction and
                // let the next scheduled pass pick the occurrence up again.
                throw new \RuntimeException(
                    'The recurring journal occurrence was claimed by another worker.',
                    0,
                    $e
                );
            }
        });
    }

    /**
     * A completed occurrence is never generated again. A failed or stale
     * attempt may be retried; a fresh pending row belongs to another worker.
     */
    private function retryable(RecurringJournalExecution $execution): bool
    {
        if ($execution->isCompleted()) {
            return false;
        }

        return $execution->isFailed() || $execution->attempt_count === 0 || $execution->isStale(now());
    }

    private function findExecution(RecurringJournal $recurringJournal, Carbon $runDate): ?RecurringJournalExecution
    {
        return RecurringJournalExecution::where('organization_id', $recurringJournal->organization_id)
            ->where('recurring_journal_id', $recurringJournal->id)
            ->whereDate('scheduled_run_date', $runDate)
            ->first();
    }

    private function takeOverAttempt(RecurringJournalExecution $execution): RecurringJournalExecution
    {
        $execution->update([
            'status' => RecurringJournalExecutionStatus::Pending,
            'attempt_count' => $execution->attempt_count + 1,
            'started_at' => now(),
            'error_message' => null,
        ]);

        return $execution->fresh();
    }

    private function generate(RecurringJournal $recurringJournal, RecurringJournalExecution $execution): RecurringJournalExecution
    {
        return DB::transaction(function () use ($recurringJournal, $execution) {
            $locked = RecurringJournal::where('id', $recurringJournal->id)
                ->lockForUpdate()
                ->firstOrFail();

            $execution = RecurringJournalExecution::where('id', $execution->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $execution->isPending()) {
                return $execution;
            }

            $organization = $locked->organization;
            $runDate = $execution->scheduled_run_date;
            $entry = $this->ensureJournalEntry($locked, $organization, $runDate);

            if ($locked->auto_submit && $entry->isDraft()) {
                (new SubmitJournalEntry)->handle($entry, $locked->creator);
            }

            $execution->update([
                'status' => RecurringJournalExecutionStatus::Completed,
                'journal_entry_id' => $entry->id,
                'completed_at' => now(),
                'error_message' => null,
            ]);

            $nextRunDate = $locked->nextOccurrenceAfter($runDate);
            $scheduleFinished = $locked->scheduleExhausted($nextRunDate);

            $locked->update([
                'last_run_at' => now(),
                'last_run_key' => $execution->idempotency_key,
                'run_count' => $locked->run_count + 1,
                'next_run_date' => $nextRunDate,
                'status' => $scheduleFinished ? RecurringJournalStatus::Completed : RecurringJournalStatus::Active,
            ]);

            app(AuditLogService::class)->record(
                $organization,
                $locked->created_by,
                'recurring-journal.generated',
                RecurringJournal::class,
                $locked->id,
                null,
                [
                    'scheduled_run_date' => $runDate->toDateString(),
                    'journal_entry_id' => $entry->id,
                    'entry_number' => $entry->entry_number,
                    'entry_status' => $entry->fresh()->status->value,
                ]
            );

            return $execution->fresh();
        });
    }

    private function ensureJournalEntry(RecurringJournal $recurringJournal, Organization $organization, Carbon $runDate): JournalEntry
    {
        $idempotencyKey = $this->idempotencyKey($recurringJournal, $runDate);

        $existing = JournalEntry::forOrganization($organization)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        $journal = Journal::forOrganization($organization)
            ->where('id', $recurringJournal->journal_id)
            ->first();

        if (! $journal || ! $journal->isActive()) {
            throw new \RuntimeException('The recurring journal target journal is missing or inactive.');
        }

        // Hold the journal row so the minted entry number cannot collide with a
        // concurrent entry for the same journal.
        Journal::where('id', $journal->id)->lockForUpdate()->first();

        [$fiscalYear, $period] = $this->resolvePeriod($organization, $runDate, 'scheduled date');

        return (new CreateJournalEntry)->handle([
            'journal_id' => $journal->id,
            'currency_id' => $organization->base_currency_id,
            'fiscal_year_id' => $fiscalYear->id,
            'accounting_period_id' => $period->id,
            'entry_date' => $runDate->toDateString(),
            'description' => $recurringJournal->name,
            'reference' => $idempotencyKey,
            'exchange_rate' => 1,
            'idempotency_key' => $idempotencyKey,
            'lines' => $recurringJournal->lines()
                ->orderBy('line_number')
                ->get()
                ->map(fn ($line) => [
                    'account_id' => $line->account_id,
                    'description' => $line->description,
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                ])
                ->all(),
        ], $organization, $recurringJournal->creator);
    }

    private function markFailed(RecurringJournalExecution $execution, \Throwable $e): void
    {
        DB::transaction(function () use ($execution, $e) {
            $locked = RecurringJournalExecution::where('id', $execution->id)
                ->lockForUpdate()
                ->first();

            if ($locked && ! $locked->isCompleted()) {
                $locked->update([
                    'status' => RecurringJournalExecutionStatus::Failed,
                    'error_message' => Str::limit($e->getMessage(), 1000),
                ]);
            }
        });
    }

    private function idempotencyKey(RecurringJournal $recurringJournal, Carbon $runDate): string
    {
        return 'recurring-journal:'.$recurringJournal->id.':'.$runDate->toDateString();
    }

    public function rules(): array
    {
        return [];
    }
}
