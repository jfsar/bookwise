<?php

namespace App\Actions\RecurringJournal;

use App\Actions\Action;
use App\Actions\Concerns\ValidatesRecurringJournalLines;
use App\Enums\RecurringJournalFrequency;
use App\Enums\RecurringJournalStatus;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\RecurringJournal;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreateRecurringJournal
{
    use Action, ValidatesRecurringJournalLines;

    public function handle(array $data, Organization $organization, User $creator): RecurringJournal
    {
        return DB::transaction(function () use ($data, $organization, $creator) {
            $journal = Journal::where('id', $data['journal_id'])
                ->where('organization_id', $organization->id)
                ->first();

            if (! $journal) {
                throw new \RuntimeException('The journal must belong to the same organization.');
            }

            if (! $journal->isActive()) {
                throw new \RuntimeException('The journal must be active.');
            }

            $validatedLines = $this->validatedLines($data['lines'] ?? [], $organization);

            $startDate = Carbon::parse($data['start_date'])->startOfDay();
            $endDate = null;

            if (! empty($data['end_date'])) {
                $endDate = Carbon::parse($data['end_date'])->startOfDay();

                if ($endDate->lessThan($startDate)) {
                    throw new \RuntimeException('The end date cannot be before the start date.');
                }
            }

            $frequency = RecurringJournalFrequency::from($data['frequency']);
            $autoSubmit = (bool) ($data['auto_submit'] ?? false);

            $recurringJournal = RecurringJournal::create([
                'organization_id' => $organization->id,
                'journal_id' => $journal->id,
                'name' => $data['name'],
                'description' => $this->nullableText($data['description'] ?? null),
                'frequency' => $frequency,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'next_run_date' => $startDate,
                'status' => RecurringJournalStatus::Active,
                'auto_submit' => $autoSubmit,
                'created_by' => $creator->id,
            ]);

            $this->replaceLines($recurringJournal, $validatedLines);

            app(AuditLogService::class)->record(
                $organization,
                $creator->id,
                'recurring-journal.created',
                RecurringJournal::class,
                $recurringJournal->id,
                null,
                [
                    'name' => $recurringJournal->name,
                    'journal_id' => $recurringJournal->journal_id,
                    'frequency' => $recurringJournal->frequency->value,
                    'start_date' => $recurringJournal->start_date->toDateString(),
                    'end_date' => $recurringJournal->end_date?->toDateString(),
                    'auto_submit' => $autoSubmit,
                ] + $this->totals($validatedLines)
            );

            return $recurringJournal->fresh(['lines']);
        });
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'journal_id' => ['required', 'integer', 'exists:journals,id'],
            'frequency' => ['required', 'string', 'max:30'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'auto_submit' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.debit' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'gte:0'],
        ];
    }
}
