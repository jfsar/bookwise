<?php

namespace Tests\Feature;

use App\Enums\AccountingPeriodStatus;
use App\Enums\RecurringJournalStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\RecurringJournal;
use App\Models\RecurringJournalLine;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RecurringJournalTest extends TestCase
{
    use RefreshDatabase;

    private function attachRoleWithPermission(User $user, Organization $organization, string|array $permissionNames): void
    {
        $user->organizations()->attach($organization->id, ['membership_status' => 'active']);

        $role = Role::create([
            'name' => 'Tester',
            'slug' => 'tester-'.uniqid(),
            'is_global' => false,
        ]);

        $role->permissions()->attach(
            collect($permissionNames)->map(fn (string $name) => Permission::firstOrCreate(['name' => $name])->id)
        );

        $user->roles()->attach($role->id, ['organization_id' => $organization->id]);
    }

    /**
     * @return array{organization: Organization, journal: Journal, debit: Account, credit: Account, user: User}
     */
    private function recurringJournalContext(): array
    {
        $organization = Organization::factory()->create();
        $journal = Journal::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
        ]);
        $fiscalYear = FiscalYear::factory()->create([
            'organization_id' => $organization->id,
            'start_date' => today()->copy()->startOfYear()->toDateString(),
            'end_date' => today()->copy()->endOfYear()->toDateString(),
        ]);
        $period = AccountingPeriod::factory()->create([
            'organization_id' => $organization->id,
            'fiscal_year_id' => $fiscalYear->id,
            'name' => today()->copy()->format('F Y'),
            'period_number' => today()->month,
            'start_date' => today()->copy()->startOfMonth()->toDateString(),
            'end_date' => today()->copy()->endOfMonth()->toDateString(),
            'status' => AccountingPeriodStatus::Open,
        ]);

        return [
            'organization' => $organization,
            'journal' => $journal,
            'fiscalYear' => $fiscalYear,
            'period' => $period,
            'debit' => Account::factory()->create(['organization_id' => $organization->id]),
            'credit' => Account::factory()->create(['organization_id' => $organization->id]),
            'user' => User::factory()->create(),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function storePayload(array $context, array $overrides = []): array
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

    /**
     * @param  array<string, mixed>  $context
     */
    private function definition(array $context): RecurringJournal
    {
        $recurringJournal = RecurringJournal::factory()->create([
            'organization_id' => $context['organization']->id,
            'journal_id' => $context['journal']->id,
            'name' => 'Monthly Rent',
            'start_date' => today()->toDateString(),
            'next_run_date' => today()->toDateString(),
            'created_by' => $context['user']->id,
        ]);

        foreach ([[$context['debit']->id, 50000, 0], [$context['credit']->id, 0, 50000]] as $index => [$accountId, $debit, $credit]) {
            RecurringJournalLine::create([
                'organization_id' => $recurringJournal->organization_id,
                'recurring_journal_id' => $recurringJournal->id,
                'account_id' => $accountId,
                'line_number' => $index + 1,
                'debit' => $debit,
                'credit' => $credit,
            ]);
        }

        return $recurringJournal;
    }

    public function test_index_requires_authentication(): void
    {
        $this->get('/recurring-journals')->assertRedirect(route('login'));
    }

    public function test_index_forbids_user_without_permission(): void
    {
        $context = $this->recurringJournalContext();
        $this->attachRoleWithPermission($context['user'], $context['organization'], 'view-journals');

        $this->actingAs($context['user'])->get('/recurring-journals')->assertStatus(403);
    }

    public function test_index_lists_only_the_definitions_of_the_current_organization(): void
    {
        $context = $this->recurringJournalContext();
        $this->attachRoleWithPermission($context['user'], $context['organization'], 'view-recurring-journals');
        $recurringJournal = $this->definition($context);
        RecurringJournal::factory()->create();

        $this->actingAs($context['user'])
            ->get('/recurring-journals')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('RecurringJournals/Index')
                ->where('canCreate', false)
                ->has('journals', 1)
                ->where('journals.0.id', $context['journal']->id)
                ->has('accounts', 2)
                ->where('statuses.0.value', 'active')
                ->has('frequencies', 5)
                ->has('recurringJournals.data', 1)
                ->where('recurringJournals.data.0.id', $recurringJournal->id)
                ->where('recurringJournals.data.0.status', RecurringJournalStatus::Active->value)
                ->where('recurringJournals.data.0.frequency', 'monthly')
                ->where('recurringJournals.data.0.next_run_date', today()->toDateString())
                ->where('recurringJournals.data.0.total_debit', 50000)
                ->where('recurringJournals.data.0.can.run', false)
                ->has('recurringJournals.data.0.lines', 2)
            );
    }

    public function test_index_filters_by_status_and_search_term(): void
    {
        $context = $this->recurringJournalContext();
        $this->attachRoleWithPermission($context['user'], $context['organization'], 'view-recurring-journals');
        $recurringJournal = $this->definition($context);
        RecurringJournal::factory()->paused()->create([
            'organization_id' => $context['organization']->id,
            'journal_id' => $context['journal']->id,
        ]);

        $this->actingAs($context['user'])
            ->get('/recurring-journals?status=active&search=rent')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('recurringJournals.data', 1)
                ->where('recurringJournals.data.0.id', $recurringJournal->id)
            );

        $this->actingAs($context['user'])
            ->get('/recurring-journals?status=paused&search=rent')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('recurringJournals.data', 0));
    }

    public function test_index_reports_creation_permission(): void
    {
        $context = $this->recurringJournalContext();
        $this->attachRoleWithPermission($context['user'], $context['organization'], [
            'view-recurring-journals',
            'create-recurring-journals',
        ]);

        $this->actingAs($context['user'])
            ->get('/recurring-journals')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canCreate', true));
    }

    public function test_store_forbids_user_without_permission(): void
    {
        $context = $this->recurringJournalContext();
        $this->attachRoleWithPermission($context['user'], $context['organization'], 'view-recurring-journals');

        $this->actingAs($context['user']);
        $this->confirmPassword();

        $this->from('/recurring-journals')
            ->post('/recurring-journals', $this->storePayload($context))
            ->assertStatus(403);

        $this->assertDatabaseCount('recurring_journals', 0);
    }

    public function test_store_creates_the_definition_with_its_lines(): void
    {
        $context = $this->recurringJournalContext();
        $this->attachRoleWithPermission($context['user'], $context['organization'], 'create-recurring-journals');

        $this->actingAs($context['user']);
        $this->confirmPassword();

        $this->from('/recurring-journals')
            ->post('/recurring-journals', $this->storePayload($context))
            ->assertRedirect('/recurring-journals')
            ->assertSessionHas('success');

        $recurringJournal = RecurringJournal::firstOrFail();

        $this->assertSame('Monthly Rent', $recurringJournal->name);
        $this->assertSame($context['organization']->id, $recurringJournal->organization_id);
        $this->assertSame($context['user']->id, $recurringJournal->created_by);
        $this->assertSame(RecurringJournalStatus::Active, $recurringJournal->status);
        $this->assertSame(today()->toDateString(), $recurringJournal->next_run_date->toDateString());
        $this->assertSame(2, $recurringJournal->lines()->count());
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $context['organization']->id,
            'action' => 'recurring-journal.created',
            'auditable_id' => $recurringJournal->id,
        ]);
    }

    public function test_store_rejects_lines_that_do_not_balance(): void
    {
        $context = $this->recurringJournalContext();
        $this->attachRoleWithPermission($context['user'], $context['organization'], 'create-recurring-journals');

        $this->actingAs($context['user']);
        $this->confirmPassword();

        $this->from('/recurring-journals')
            ->post('/recurring-journals', $this->storePayload($context, [
                'lines' => [
                    ['account_id' => $context['debit']->id, 'debit' => 50000, 'credit' => 0],
                    ['account_id' => $context['credit']->id, 'debit' => 0, 'credit' => 40000],
                ],
            ]))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'balance'));

        $this->assertDatabaseCount('recurring_journals', 0);
    }

    public function test_store_rejects_a_journal_from_another_organization(): void
    {
        $context = $this->recurringJournalContext();
        $this->attachRoleWithPermission($context['user'], $context['organization'], 'create-recurring-journals');

        $this->actingAs($context['user']);
        $this->confirmPassword();

        $foreignJournal = Journal::factory()->create();

        $this->from('/recurring-journals')
            ->post('/recurring-journals', $this->storePayload($context, ['journal_id' => $foreignJournal->id]))
            ->assertSessionHasErrors('journal_id');

        $this->assertDatabaseCount('recurring_journals', 0);
    }

    public function test_store_rejects_an_account_from_another_organization(): void
    {
        $context = $this->recurringJournalContext();
        $this->attachRoleWithPermission($context['user'], $context['organization'], 'create-recurring-journals');

        $this->actingAs($context['user']);
        $this->confirmPassword();

        $foreignAccount = Account::factory()->create();

        $this->from('/recurring-journals')
            ->post('/recurring-journals', $this->storePayload($context, [
                'lines' => [
                    ['account_id' => $foreignAccount->id, 'debit' => 50000, 'credit' => 0],
                    ['account_id' => $context['credit']->id, 'debit' => 0, 'credit' => 50000],
                ],
            ]))
            ->assertSessionHasErrors('lines.0.account_id');

        $this->assertDatabaseCount('recurring_journals', 0);
    }

    public function test_update_forbids_user_without_permission(): void
    {
        $context = $this->recurringJournalContext();
        $this->attachRoleWithPermission($context['user'], $context['organization'], 'view-recurring-journals');
        $recurringJournal = $this->definition($context);

        $this->actingAs($context['user']);
        $this->confirmPassword();

        $this->from('/recurring-journals')
            ->put("/recurring-journals/{$recurringJournal->id}", ['name' => 'Hijacked'])
            ->assertStatus(403);

        $this->assertSame('Monthly Rent', $recurringJournal->fresh()->name);
    }

    public function test_update_replaces_the_definition(): void
    {
        $context = $this->recurringJournalContext();
        $this->attachRoleWithPermission($context['user'], $context['organization'], 'update-recurring-journals');
        $recurringJournal = $this->definition($context);

        $this->actingAs($context['user']);
        $this->confirmPassword();

        $this->from('/recurring-journals')
            ->put("/recurring-journals/{$recurringJournal->id}", [
                'name' => 'Quarterly Rent',
                'frequency' => 'quarterly',
                'auto_submit' => true,
                'lines' => [
                    ['account_id' => $context['debit']->id, 'debit' => 120000, 'credit' => 0],
                    ['account_id' => $context['credit']->id, 'debit' => 0, 'credit' => 120000],
                ],
            ])
            ->assertRedirect('/recurring-journals')
            ->assertSessionHas('success');

        $recurringJournal->refresh();
        $this->assertSame('Quarterly Rent', $recurringJournal->name);
        $this->assertSame('quarterly', $recurringJournal->frequency->value);
        $this->assertTrue($recurringJournal->auto_submit);
        $this->assertSame(2, $recurringJournal->lines()->count());
        $this->assertSame(120000.0, $recurringJournal->totalDebit());
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $context['organization']->id,
            'action' => 'recurring-journal.updated',
            'auditable_id' => $recurringJournal->id,
        ]);
    }

    public function test_pause_and_resume_endpoints_toggle_the_status(): void
    {
        $context = $this->recurringJournalContext();
        $this->attachRoleWithPermission($context['user'], $context['organization'], 'update-recurring-journals');
        $recurringJournal = $this->definition($context);

        $this->actingAs($context['user']);
        $this->confirmPassword();

        $this->from('/recurring-journals')
            ->post("/recurring-journals/{$recurringJournal->id}/pause")
            ->assertRedirect('/recurring-journals');

        $this->assertSame(RecurringJournalStatus::Paused, $recurringJournal->fresh()->status);

        $this->from('/recurring-journals')
            ->post("/recurring-journals/{$recurringJournal->id}/resume")
            ->assertRedirect('/recurring-journals');

        $this->assertSame(RecurringJournalStatus::Active, $recurringJournal->fresh()->status);
    }

    public function test_pause_is_forbidden_once_the_definition_is_paused(): void
    {
        $context = $this->recurringJournalContext();
        $this->attachRoleWithPermission($context['user'], $context['organization'], 'update-recurring-journals');
        $recurringJournal = $this->definition($context);

        $this->actingAs($context['user']);
        $this->confirmPassword();

        $this->from('/recurring-journals')
            ->post("/recurring-journals/{$recurringJournal->id}/pause")
            ->assertSessionHas('success');

        $this->from('/recurring-journals')
            ->post("/recurring-journals/{$recurringJournal->id}/pause")
            ->assertStatus(403);

        $this->assertSame(RecurringJournalStatus::Paused, $recurringJournal->fresh()->status);
    }

    public function test_resume_is_forbidden_while_the_definition_is_active(): void
    {
        $context = $this->recurringJournalContext();
        $this->attachRoleWithPermission($context['user'], $context['organization'], 'update-recurring-journals');
        $recurringJournal = $this->definition($context);

        $this->actingAs($context['user']);
        $this->confirmPassword();

        $this->from('/recurring-journals')
            ->post("/recurring-journals/{$recurringJournal->id}/resume")
            ->assertStatus(403);

        $this->assertSame(RecurringJournalStatus::Active, $recurringJournal->fresh()->status);
    }

    public function test_delete_forbids_user_without_permission(): void
    {
        $context = $this->recurringJournalContext();
        $this->attachRoleWithPermission($context['user'], $context['organization'], 'view-recurring-journals');
        $recurringJournal = $this->definition($context);

        $this->actingAs($context['user']);
        $this->confirmPassword();

        $this->from('/recurring-journals')
            ->delete("/recurring-journals/{$recurringJournal->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('recurring_journals', ['id' => $recurringJournal->id]);
    }

    public function test_delete_is_forbidden_while_the_definition_is_active(): void
    {
        $context = $this->recurringJournalContext();
        $this->attachRoleWithPermission($context['user'], $context['organization'], 'delete-recurring-journals');
        $recurringJournal = $this->definition($context);

        $this->actingAs($context['user']);
        $this->confirmPassword();

        $this->from('/recurring-journals')
            ->delete("/recurring-journals/{$recurringJournal->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('recurring_journals', ['id' => $recurringJournal->id]);
    }

    public function test_delete_removes_a_paused_definition(): void
    {
        $context = $this->recurringJournalContext();
        $this->attachRoleWithPermission($context['user'], $context['organization'], [
            'update-recurring-journals',
            'delete-recurring-journals',
        ]);
        $recurringJournal = $this->definition($context);

        $this->actingAs($context['user']);
        $this->confirmPassword();

        $this->from('/recurring-journals')
            ->post("/recurring-journals/{$recurringJournal->id}/pause");

        $this->from('/recurring-journals')
            ->delete("/recurring-journals/{$recurringJournal->id}")
            ->assertRedirect('/recurring-journals')
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('recurring_journals', ['id' => $recurringJournal->id]);
        $this->assertDatabaseMissing('recurring_journal_lines', ['recurring_journal_id' => $recurringJournal->id]);
    }

    public function test_definition_of_another_organization_is_not_reachable(): void
    {
        $context = $this->recurringJournalContext();
        $this->attachRoleWithPermission($context['user'], $context['organization'], [
            'update-recurring-journals',
            'delete-recurring-journals',
        ]);

        $foreignDefinition = RecurringJournal::factory()->paused()->create();

        $this->actingAs($context['user']);
        $this->confirmPassword();

        $this->put("/recurring-journals/{$foreignDefinition->id}", ['name' => 'Hijacked'])->assertStatus(404);
        $this->delete("/recurring-journals/{$foreignDefinition->id}")->assertStatus(404);

        $this->assertDatabaseHas('recurring_journals', ['id' => $foreignDefinition->id]);
    }

    public function test_run_endpoint_requires_permission(): void
    {
        $context = $this->recurringJournalContext();
        $this->attachRoleWithPermission($context['user'], $context['organization'], 'view-recurring-journals');
        $recurringJournal = $this->definition($context);

        $this->actingAs($context['user']);
        $this->confirmPassword();

        $this->from('/recurring-journals')
            ->post("/recurring-journals/{$recurringJournal->id}/run")
            ->assertStatus(403);

        $this->assertDatabaseCount('recurring_journal_executions', 0);
    }

    public function test_run_endpoint_generates_the_due_occurrence(): void
    {
        $context = $this->recurringJournalContext();
        $this->attachRoleWithPermission($context['user'], $context['organization'], 'update-recurring-journals');
        $recurringJournal = $this->definition($context);

        $this->actingAs($context['user']);
        $this->confirmPassword();

        $this->from('/recurring-journals')
            ->post("/recurring-journals/{$recurringJournal->id}/run")
            ->assertRedirect('/recurring-journals')
            ->assertSessionHas('success');

        $this->assertDatabaseCount('recurring_journal_executions', 1);
        $this->assertDatabaseHas('recurring_journal_executions', [
            'recurring_journal_id' => $recurringJournal->id,
            'status' => 'completed',
        ]);
    }
}
