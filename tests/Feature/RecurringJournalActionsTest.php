<?php

namespace Tests\Feature;

use App\Actions\RecurringJournal\CreateRecurringJournal;
use App\Actions\RecurringJournal\DeleteRecurringJournal;
use App\Actions\RecurringJournal\GenerateRecurringJournal;
use App\Actions\RecurringJournal\PauseRecurringJournal;
use App\Actions\RecurringJournal\ResumeRecurringJournal;
use App\Actions\RecurringJournal\RunDueRecurringJournals;
use App\Actions\RecurringJournal\UpdateRecurringJournal;
use App\Enums\AccountingPeriodStatus;
use App\Enums\JournalEntryStatus;
use App\Enums\RecurringJournalExecutionStatus;
use App\Enums\RecurringJournalStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Organization;
use App\Models\RecurringJournal;
use App\Models\RecurringJournalExecution;
use App\Models\RecurringJournalLine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringJournalActionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{organization: Organization, journal: Journal, debit: Account, credit: Account, fiscalYear: FiscalYear, period: AccountingPeriod, user: User}
     */
    private function accountingContext(): array
    {
        $organization = Organization::factory()->create();
        $journal = Journal::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
        ]);
        $debit = Account::factory()->create(['organization_id' => $organization->id]);
        $credit = Account::factory()->create(['organization_id' => $organization->id]);
        $fiscalYear = FiscalYear::factory()->create([
            'organization_id' => $organization->id,
            'start_date' => today()->copy()->startOfYear()->toDateString(),
            'end_date' => today()->copy()->endOfYear()->toDateString(),
        ]);

        return [
            'organization' => $organization,
            'journal' => $journal,
            'debit' => $debit,
            'credit' => $credit,
            'fiscalYear' => $fiscalYear,
            'period' => $this->openPeriod($organization, $fiscalYear, today()),
            'user' => User::factory()->create(),
        ];
    }

    private function openPeriod(Organization $organization, FiscalYear $fiscalYear, Carbon $month): AccountingPeriod
    {
        return AccountingPeriod::factory()->create([
            'organization_id' => $organization->id,
            'fiscal_year_id' => $fiscalYear->id,
            'name' => $month->copy()->format('F Y'),
            'period_number' => $month->month,
            'start_date' => $month->copy()->startOfMonth()->toDateString(),
            'end_date' => $month->copy()->endOfMonth()->toDateString(),
            'status' => AccountingPeriodStatus::Open,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function definition(array $context, array $overrides = []): RecurringJournal
    {
        $recurringJournal = RecurringJournal::factory()->create(array_merge([
            'organization_id' => $context['organization']->id,
            'journal_id' => $context['journal']->id,
            'start_date' => today()->toDateString(),
            'next_run_date' => today()->toDateString(),
            'created_by' => $context['user']->id,
        ], $overrides));

        $this->addLines($recurringJournal, $context);

        return $recurringJournal->fresh(['lines']);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function addLines(RecurringJournal $recurringJournal, array $context, float $amount = 50000): void
    {
        RecurringJournalLine::create([
            'organization_id' => $recurringJournal->organization_id,
            'recurring_journal_id' => $recurringJournal->id,
            'account_id' => $context['debit']->id,
            'line_number' => 1,
            'debit' => $amount,
            'credit' => 0,
        ]);

        RecurringJournalLine::create([
            'organization_id' => $recurringJournal->organization_id,
            'recurring_journal_id' => $recurringJournal->id,
            'account_id' => $context['credit']->id,
            'line_number' => 2,
            'debit' => 0,
            'credit' => $amount,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function createPayload(array $context, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Monthly Rent',
            'description' => 'Shop rental',
            'journal_id' => $context['journal']->id,
            'frequency' => 'monthly',
            'start_date' => today()->toDateString(),
            'auto_submit' => false,
            'lines' => [
                ['account_id' => $context['debit']->id, 'description' => 'Rent expense', 'debit' => 50000, 'credit' => 0],
                ['account_id' => $context['credit']->id, 'description' => 'Rent payable', 'debit' => 0, 'credit' => 50000],
            ],
        ], $overrides);
    }

    public function test_create_persists_the_definition_lines_and_audit_record(): void
    {
        $context = $this->accountingContext();

        $recurringJournal = (new CreateRecurringJournal)->handle($this->createPayload($context), $context['organization'], $context['user']);

        $this->assertSame('Monthly Rent', $recurringJournal->name);
        $this->assertSame(RecurringJournalStatus::Active, $recurringJournal->status);
        $this->assertSame(today()->toDateString(), $recurringJournal->next_run_date->toDateString());
        $this->assertCount(2, $recurringJournal->lines);
        $this->assertTrue($recurringJournal->isBalanced());
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $context['organization']->id,
            'action' => 'recurring-journal.created',
            'auditable_type' => RecurringJournal::class,
            'auditable_id' => $recurringJournal->id,
        ]);
    }

    public function test_create_rejects_unbalanced_lines(): void
    {
        $context = $this->accountingContext();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Recurring journal lines must balance');

        (new CreateRecurringJournal)->handle($this->createPayload($context, [
            'lines' => [
                ['account_id' => $context['debit']->id, 'debit' => 50000, 'credit' => 0],
                ['account_id' => $context['credit']->id, 'debit' => 0, 'credit' => 40000],
            ],
        ]), $context['organization'], $context['user']);
    }

    public function test_create_rejects_a_line_that_is_both_debit_and_credit(): void
    {
        $context = $this->accountingContext();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot be both a debit and a credit');

        (new CreateRecurringJournal)->handle($this->createPayload($context, [
            'lines' => [
                ['account_id' => $context['debit']->id, 'debit' => 50000, 'credit' => 1000],
                ['account_id' => $context['credit']->id, 'debit' => 0, 'credit' => 50000],
            ],
        ]), $context['organization'], $context['user']);
    }

    public function test_create_rejects_an_account_from_another_organization(): void
    {
        $context = $this->accountingContext();
        $foreignAccount = Account::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('same organization');

        (new CreateRecurringJournal)->handle($this->createPayload($context, [
            'lines' => [
                ['account_id' => $foreignAccount->id, 'debit' => 50000, 'credit' => 0],
                ['account_id' => $context['credit']->id, 'debit' => 0, 'credit' => 50000],
            ],
        ]), $context['organization'], $context['user']);
    }

    public function test_create_rejects_a_non_posting_account(): void
    {
        $context = $this->accountingContext();
        $inactiveAccount = Account::factory()->inactive()->create(['organization_id' => $context['organization']->id]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('active posting account');

        (new CreateRecurringJournal)->handle($this->createPayload($context, [
            'lines' => [
                ['account_id' => $inactiveAccount->id, 'debit' => 50000, 'credit' => 0],
                ['account_id' => $context['credit']->id, 'debit' => 0, 'credit' => 50000],
            ],
        ]), $context['organization'], $context['user']);
    }

    public function test_create_rejects_an_end_date_before_the_start_date(): void
    {
        $context = $this->accountingContext();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('end date cannot be before the start date');

        (new CreateRecurringJournal)->handle($this->createPayload($context, [
            'end_date' => today()->subMonth()->toDateString(),
        ]), $context['organization'], $context['user']);
    }

    public function test_generation_creates_one_balanced_draft_entry_and_advances_the_schedule(): void
    {
        $context = $this->accountingContext();
        $recurringJournal = $this->definition($context);

        $execution = (new GenerateRecurringJournal)->handle($recurringJournal);

        $this->assertSame(RecurringJournalExecutionStatus::Completed, $execution->status);
        $this->assertSame(today()->toDateString(), $execution->scheduled_run_date->toDateString());
        $this->assertSame(1, $execution->attempt_count);

        $entry = $execution->journalEntry;
        $this->assertSame(JournalEntryStatus::Draft, $entry->status);
        $this->assertSame($context['journal']->id, $entry->journal_id);
        $this->assertSame($context['organization']->id, $entry->organization_id);
        $this->assertTrue($entry->isBalanced());
        $this->assertSame(2, $entry->lines()->count());

        $recurringJournal->refresh();
        $this->assertSame(1, $recurringJournal->run_count);
        $this->assertSame(today()->addMonthNoOverflow()->startOfMonth()->toDateString(), $recurringJournal->next_run_date->toDateString());
        $this->assertNotNull($recurringJournal->last_run_at);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $context['organization']->id,
            'action' => 'recurring-journal.generated',
            'auditable_type' => RecurringJournal::class,
            'auditable_id' => $recurringJournal->id,
        ]);
    }

    public function test_generation_is_idempotent_for_the_same_scheduled_date(): void
    {
        $context = $this->accountingContext();
        $recurringJournal = $this->definition($context);

        $first = (new GenerateRecurringJournal)->handle($recurringJournal);
        $second = (new GenerateRecurringJournal)->handle($recurringJournal->fresh(), today());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, RecurringJournalExecution::where('recurring_journal_id', $recurringJournal->id)->count());
        $this->assertSame(1, JournalEntry::where('organization_id', $context['organization']->id)->count());
        $this->assertSame(1, $recurringJournal->fresh()->run_count);
    }

    public function test_a_pending_execution_owned_by_another_worker_is_not_generated_again(): void
    {
        $context = $this->accountingContext();
        $recurringJournal = $this->definition($context);

        $pending = RecurringJournalExecution::factory()->create([
            'organization_id' => $recurringJournal->organization_id,
            'recurring_journal_id' => $recurringJournal->id,
            'scheduled_run_date' => today()->toDateString(),
            'status' => RecurringJournalExecutionStatus::Pending,
            'started_at' => now(),
        ]);

        $execution = (new GenerateRecurringJournal)->handle($recurringJournal);

        $this->assertSame($pending->id, $execution->id);
        $this->assertSame(1, $execution->attempt_count);
        $this->assertSame(0, JournalEntry::count());
        $this->assertSame(0, $recurringJournal->fresh()->run_count);
    }

    public function test_a_stale_pending_execution_is_taken_over_and_retried(): void
    {
        $context = $this->accountingContext();
        $recurringJournal = $this->definition($context);

        RecurringJournalExecution::factory()->create([
            'organization_id' => $recurringJournal->organization_id,
            'recurring_journal_id' => $recurringJournal->id,
            'scheduled_run_date' => today()->toDateString(),
            'status' => RecurringJournalExecutionStatus::Pending,
            'attempt_count' => 1,
            'started_at' => now()->subHours(3),
        ]);

        $execution = (new GenerateRecurringJournal)->handle($recurringJournal);

        $this->assertSame(RecurringJournalExecutionStatus::Completed, $execution->status);
        $this->assertSame(2, $execution->attempt_count);
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_auto_submit_only_submits_the_entry_and_never_posts_it(): void
    {
        $context = $this->accountingContext();
        $recurringJournal = $this->definition($context, ['auto_submit' => true]);

        $execution = (new GenerateRecurringJournal)->handle($recurringJournal);

        $entry = $execution->journalEntry->fresh();
        $this->assertSame(JournalEntryStatus::Submitted, $entry->status);
        $this->assertNotNull($entry->submitted_at);
        $this->assertNull($entry->approved_at);
        $this->assertNull($entry->posted_at);
    }

    public function test_a_closed_period_fails_the_execution_and_a_later_run_retries_it(): void
    {
        $context = $this->accountingContext();
        $recurringJournal = $this->definition($context);

        $context['period']->update(['status' => AccountingPeriodStatus::Closed]);

        try {
            (new GenerateRecurringJournal)->handle($recurringJournal);
            $this->fail('Generation should fail while the accounting period is closed.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('open accounting period', $e->getMessage());
        }

        $execution = RecurringJournalExecution::where('recurring_journal_id', $recurringJournal->id)->firstOrFail();
        $this->assertSame(RecurringJournalExecutionStatus::Failed, $execution->status);
        $this->assertNotNull($execution->error_message);
        $this->assertSame(0, $recurringJournal->fresh()->run_count);
        $this->assertSame(today()->toDateString(), $recurringJournal->fresh()->next_run_date->toDateString());
        $this->assertSame(0, JournalEntry::count());

        $context['period']->update(['status' => AccountingPeriodStatus::Open]);

        $retried = (new GenerateRecurringJournal)->handle($recurringJournal->fresh());

        $this->assertSame(RecurringJournalExecutionStatus::Completed, $retried->status);
        $this->assertSame(2, $retried->attempt_count);
        $this->assertSame(1, JournalEntry::count());
        $this->assertSame(1, $recurringJournal->fresh()->run_count);
    }

    public function test_generation_is_refused_while_the_definition_is_paused(): void
    {
        $context = $this->accountingContext();
        $recurringJournal = $this->definition($context, ['status' => RecurringJournalStatus::Paused]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Only an active recurring journal can generate entries');

        (new GenerateRecurringJournal)->handle($recurringJournal);
    }

    public function test_generation_is_refused_outside_the_effective_dates(): void
    {
        $context = $this->accountingContext();
        $recurringJournal = $this->definition($context, [
            'start_date' => today()->toDateString(),
            'next_run_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('outside the recurring journal date range');

        (new GenerateRecurringJournal)->handle($recurringJournal, today()->subDay());
    }

    public function test_run_due_catches_up_every_missed_occurrence(): void
    {
        $context = $this->accountingContext();
        $this->openPeriod($context['organization'], $context['fiscalYear'], today()->subMonth());
        $this->openPeriod($context['organization'], $context['fiscalYear'], today()->subMonths(2));

        $recurringJournal = $this->definition($context, [
            'start_date' => today()->copy()->subMonths(2)->startOfMonth()->toDateString(),
            'next_run_date' => today()->copy()->subMonths(2)->startOfMonth()->toDateString(),
        ]);

        $summary = (new RunDueRecurringJournals)->handle();

        $this->assertSame([], $summary['failures']);
        $this->assertSame(1, $summary['definitions']);
        $this->assertSame(3, $summary['generated']);
        $this->assertSame(0, $summary['failed']);
        $this->assertSame(3, JournalEntry::where('organization_id', $context['organization']->id)->count());
        $this->assertSame(3, RecurringJournalExecution::where('recurring_journal_id', $recurringJournal->id)->count());
        $this->assertSame(3, $recurringJournal->fresh()->run_count);
        $this->assertSame(
            today()->copy()->addMonthNoOverflow()->startOfMonth()->toDateString(),
            $recurringJournal->fresh()->next_run_date->toDateString()
        );
    }

    public function test_run_due_completes_a_definition_once_its_end_date_passes(): void
    {
        $context = $this->accountingContext();
        $recurringJournal = $this->definition($context, [
            'start_date' => today()->toDateString(),
            'next_run_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
        ]);

        $summary = (new RunDueRecurringJournals)->handle();

        $this->assertSame(1, $summary['generated']);
        $this->assertSame(RecurringJournalStatus::Completed, $recurringJournal->fresh()->status);
        $this->assertSame(1, JournalEntry::count());

        $secondPass = (new RunDueRecurringJournals)->handle();
        $this->assertSame(0, $secondPass['definitions']);
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_run_due_reports_failures_without_generating_anything(): void
    {
        $context = $this->accountingContext();
        $recurringJournal = $this->definition($context);

        $context['period']->update(['status' => AccountingPeriodStatus::Closed]);

        $summary = (new RunDueRecurringJournals)->handle();

        $this->assertSame(1, $summary['failed']);
        $this->assertSame(0, $summary['generated']);
        $this->assertNotEmpty($summary['failures']);
        $this->assertSame(0, JournalEntry::count());
        $this->assertSame(RecurringJournalStatus::Active, $recurringJournal->fresh()->status);
    }

    public function test_command_generates_the_due_entries(): void
    {
        $context = $this->accountingContext();
        $this->definition($context);

        $this->artisan('recurring-journals:generate')
            ->expectsOutputToContain('1 generated')
            ->assertExitCode(0);

        $this->assertSame(1, JournalEntry::count());
    }

    public function test_command_exits_with_failure_when_an_occurrence_cannot_be_generated(): void
    {
        $context = $this->accountingContext();
        $this->definition($context);
        $context['period']->update(['status' => AccountingPeriodStatus::Closed]);

        $this->artisan('recurring-journals:generate')->assertExitCode(1);
    }

    public function test_pause_and_resume_toggle_the_status_and_are_audited(): void
    {
        $context = $this->accountingContext();
        $recurringJournal = $this->definition($context);

        (new PauseRecurringJournal)->handle($recurringJournal, $context['user']);
        $this->assertSame(RecurringJournalStatus::Paused, $recurringJournal->fresh()->status);

        (new ResumeRecurringJournal)->handle($recurringJournal->fresh(), $context['user']);
        $this->assertSame(RecurringJournalStatus::Active, $recurringJournal->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $context['organization']->id,
            'action' => 'recurring-journal.paused',
            'auditable_id' => $recurringJournal->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $context['organization']->id,
            'action' => 'recurring-journal.resumed',
            'auditable_id' => $recurringJournal->id,
        ]);
    }

    public function test_update_replaces_lines_and_rebases_the_schedule_when_the_frequency_changes(): void
    {
        $context = $this->accountingContext();
        $recurringJournal = $this->definition($context);
        $recurringJournal->update(['next_run_date' => today()->addMonth()->startOfMonth()]);

        (new UpdateRecurringJournal)->handle($recurringJournal, [
            'name' => 'Quarterly Rent',
            'frequency' => 'quarterly',
            'lines' => [
                ['account_id' => $context['debit']->id, 'debit' => 150000, 'credit' => 0],
                ['account_id' => $context['credit']->id, 'debit' => 0, 'credit' => 150000],
            ],
        ], $context['user']);

        $recurringJournal->refresh();
        $this->assertSame('Quarterly Rent', $recurringJournal->name);
        $this->assertSame('quarterly', $recurringJournal->frequency->value);
        $this->assertSame(today()->toDateString(), $recurringJournal->next_run_date->toDateString());
        $this->assertSame(2, $recurringJournal->lines()->count());
        $this->assertSame(150000.0, $recurringJournal->totalDebit());
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $context['organization']->id,
            'action' => 'recurring-journal.updated',
            'auditable_id' => $recurringJournal->id,
        ]);
    }

    public function test_update_refuses_a_completed_definition(): void
    {
        $context = $this->accountingContext();
        $recurringJournal = $this->definition($context, ['status' => RecurringJournalStatus::Completed]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('completed recurring journal cannot be edited');

        (new UpdateRecurringJournal)->handle($recurringJournal, ['name' => 'Renamed'], $context['user']);
    }

    public function test_delete_requires_a_paused_definition(): void
    {
        $context = $this->accountingContext();
        $recurringJournal = $this->definition($context);

        try {
            (new DeleteRecurringJournal)->handle($recurringJournal, $context['user']);
            $this->fail('An active recurring journal must not be deleted.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Pause the recurring journal', $e->getMessage());
        }

        (new PauseRecurringJournal)->handle($recurringJournal->fresh(), $context['user']);
        (new DeleteRecurringJournal)->handle($recurringJournal->fresh(), $context['user']);

        $this->assertDatabaseMissing('recurring_journals', ['id' => $recurringJournal->id]);
        $this->assertDatabaseMissing('recurring_journal_lines', ['recurring_journal_id' => $recurringJournal->id]);
    }

    public function test_delete_refuses_a_definition_that_already_generated_entries(): void
    {
        $context = $this->accountingContext();
        $recurringJournal = $this->definition($context);

        (new GenerateRecurringJournal)->handle($recurringJournal);
        (new PauseRecurringJournal)->handle($recurringJournal->fresh(), $context['user']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already produced journal entries');

        (new DeleteRecurringJournal)->handle($recurringJournal->fresh(), $context['user']);
    }

    public function test_monthly_occurrences_do_not_drift_across_short_months(): void
    {
        $context = $this->accountingContext();
        $recurringJournal = RecurringJournal::factory()->create([
            'organization_id' => $context['organization']->id,
            'journal_id' => $context['journal']->id,
            'start_date' => '2026-01-31',
            'next_run_date' => '2026-01-31',
        ]);

        $february = $recurringJournal->nextOccurrenceAfter(Carbon::parse('2026-01-31'));
        $march = $recurringJournal->nextOccurrenceAfter($february);

        $this->assertSame('2026-02-28', $february->toDateString());
        $this->assertSame('2026-03-31', $march->toDateString());
    }
}
