<?php

namespace App\Actions\RecurringJournal;

use App\Actions\Action;
use App\Actions\Concerns\ValidatesRecurringJournalLines;
use App\Enums\RecurringJournalFrequency;
use App\Models\Journal;
use App\Models\RecurringJournal;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateRecurringJournal
{
    use Action, ValidatesRecurringJournalLines;

    public function handle(RecurringJournal $recurringJournal, array $data, User $actor): RecurringJournal
    {
        return DB::transaction(function () use ($recurringJournal, $data, $actor) {
            $recurringJournal = RecurringJournal::where('id', $recurringJournal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($recurringJournal->isCompleted()) {
                throw new \RuntimeException('A completed recurring journal cannot be edited. Create a new definition instead.');
            }

            $organization = $recurringJournal->organization;

            $journalId = $recurringJournal->journal_id;

            if (! empty($data['journal_id'])) {
                $journal = Journal::where('id', $data['journal_id'])
                    ->where('organization_id', $organization->id)
                    ->first();

                if (! $journal) {
                    throw new \RuntimeException('The journal must belong to the same organization.');
                }

                if (! $journal->isActive()) {
                    throw new \RuntimeException('The journal must be active.');
                }

                $journalId = $journal->id;
            }

            $startDate = isset($data['start_date'])
                ? Carbon::parse($data['start_date'])->startOfDay()
                : $recurringJournal->start_date;

            $endDate = array_key_exists('end_date', $data)
                ? ($data['end_date'] ? Carbon::parse($data['end_date'])->startOfDay() : null)
                : $recurringJournal->end_date;

            if ($endDate !== null && $endDate->lessThan($startDate)) {
                throw new \RuntimeException('The end date cannot be before the start date.');
            }

            $frequency = isset($data['frequency'])
                ? RecurringJournalFrequency::from($data['frequency'])
                : $recurringJournal->frequency;

            $scheduleChanged = $frequency !== $recurringJournal->frequency
                || $startDate->toDateString() !== $recurringJournal->start_date->toDateString();

            $validatedLines = isset($data['lines'])
                ? $this->validatedLines($data['lines'], $organization)
                : null;

            $oldValues = $this->snapshot($recurringJournal);

            $recurringJournal->update([
                'journal_id' => $journalId,
                'name' => $data['name'] ?? $recurringJournal->name,
                'description' => array_key_exists('description', $data)
                    ? $this->nullableText($data['description'])
                    : $recurringJournal->description,
                'frequency' => $frequency,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'next_run_date' => $scheduleChanged ? $startDate : $recurringJournal->next_run_date,
                'auto_submit' => array_key_exists('auto_submit', $data)
                    ? (bool) $data['auto_submit']
                    : $recurringJournal->auto_submit,
            ]);

            if ($validatedLines !== null) {
                $this->replaceLines($recurringJournal, $validatedLines);
            }

            $recurringJournal->refresh();

            app(AuditLogService::class)->record(
                $organization,
                $actor->id,
                'recurring-journal.updated',
                RecurringJournal::class,
                $recurringJournal->id,
                $oldValues,
                $this->snapshot($recurringJournal)
            );

            return $recurringJournal->load('lines');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(RecurringJournal $recurringJournal): array
    {
        return [
            'name' => $recurringJournal->name,
            'journal_id' => $recurringJournal->journal_id,
            'frequency' => $recurringJournal->frequency->value,
            'start_date' => $recurringJournal->start_date->toDateString(),
            'end_date' => $recurringJournal->end_date?->toDateString(),
            'next_run_date' => $recurringJournal->next_run_date->toDateString(),
            'auto_submit' => $recurringJournal->auto_submit,
            'status' => $recurringJournal->status->value,
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'journal_id' => ['sometimes', 'integer', 'exists:journals,id'],
            'frequency' => ['sometimes', 'string', 'max:30'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date'],
            'auto_submit' => ['sometimes', 'boolean'],
            'lines' => ['sometimes', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.debit' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'gte:0'],
        ];
    }
}
