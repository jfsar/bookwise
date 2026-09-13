<?php

namespace Tests\Feature;

use App\Actions\JournalEntry\CreateJournalEntry;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalEntryActionsTest extends TestCase
{
    use RefreshDatabase;

    private function createDraftEntry(Organization $org, User $creator)
    {
        $journal = Journal::factory()->create(['organization_id' => $org->id]);
        $currency = Currency::factory()->create(['code' => 'USD']);
        $fiscalYear = FiscalYear::factory()->create(['organization_id' => $org->id]);
        $period = AccountingPeriod::factory()->create([
            'organization_id' => $org->id,
            'fiscal_year_id' => $fiscalYear->id,
        ]);

        $cash = Account::factory()->create(['organization_id' => $org->id]);
        $revenue = Account::factory()->create(['organization_id' => $org->id]);

        return (new CreateJournalEntry)->handle([
            'journal_id' => $journal->id,
            'currency_id' => $currency->id,
            'fiscal_year_id' => $fiscalYear->id,
            'accounting_period_id' => $period->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Test entry',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 500, 'credit' => 0, 'description' => null],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 500, 'description' => null],
            ],
        ], $org, $creator);
    }

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

    public function test_submit_forbids_user_without_permission(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);
        $entry = $this->createDraftEntry($org, $user);

        $this->actingAs($user)
            ->post("/journal-entries/{$entry->id}/submit")
            ->assertForbidden();
    }

    public function test_submit_allows_user_with_permission(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'submit-journal-entries');
        $entry = $this->createDraftEntry($org, $user);

        $this->actingAs($user)
            ->post("/journal-entries/{$entry->id}/submit")
            ->assertRedirect();

        $this->assertSame('submitted', $entry->fresh()->status->value);
    }

    public function test_reject_forbids_user_without_permission(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $entry = $this->createDraftEntry($org, $user);

        $this->actingAs($user)
            ->post("/journal-entries/{$entry->id}/reject", ['reason' => 'No good'])
            ->assertForbidden();
    }

    public function test_reject_requires_reason(): void
    {
        $org = Organization::factory()->create();
        $submitter = User::factory()->create();
        $this->attachRoleWithPermission($submitter, $org, 'submit-journal-entries');
        $entry = $this->createDraftEntry($org, $submitter);

        $this->actingAs($submitter)
            ->post("/journal-entries/{$entry->id}/submit")
            ->assertRedirect();

        $rejecter = User::factory()->create();
        $this->attachRoleWithPermission($rejecter, $org, 'reject-journal-entries');

        $this->actingAs($rejecter)
            ->post("/journal-entries/{$entry->id}/reject", ['reason' => ''])
            ->assertRedirect()
            ->assertSessionHasErrors('reason');

        $this->assertSame('submitted', $entry->fresh()->status->value);
    }

    public function test_reject_forbids_submitter_approving_own_entry(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, ['reject-journal-entries', 'submit-journal-entries']);
        $entry = $this->createDraftEntry($org, $user);

        $this->actingAs($user)
            ->post("/journal-entries/{$entry->id}/submit")
            ->assertRedirect();

        $this->actingAs($user)
            ->post("/journal-entries/{$entry->id}/reject", ['reason' => 'Sole user'])
            ->assertForbidden();
    }

    public function test_reject_allows_user_with_permission(): void
    {
        $org = Organization::factory()->create();
        $submitter = User::factory()->create();
        $this->attachRoleWithPermission($submitter, $org, 'submit-journal-entries');
        $entry = $this->createDraftEntry($org, $submitter);

        $this->actingAs($submitter)
            ->post("/journal-entries/{$entry->id}/submit")
            ->assertRedirect();

        $rejecter = User::factory()->create();
        $this->attachRoleWithPermission($rejecter, $org, 'reject-journal-entries');

        $this->actingAs($rejecter)
            ->post("/journal-entries/{$entry->id}/reject", ['reason' => 'Missing support'])
            ->assertRedirect();

        $refreshed = $entry->fresh();
        $this->assertSame('rejected', $refreshed->status->value);
        $this->assertSame('Missing support', $refreshed->rejection_reason);
    }

    public function test_http_store_creates_draft_entry(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);

        $journal = Journal::factory()->create(['organization_id' => $org->id]);
        $currency = Currency::factory()->create(['code' => 'USD']);
        $fiscalYear = FiscalYear::factory()->create(['organization_id' => $org->id]);
        $period = AccountingPeriod::factory()->create([
            'organization_id' => $org->id,
            'fiscal_year_id' => $fiscalYear->id,
        ]);
        $cash = Account::factory()->create(['organization_id' => $org->id]);
        $revenue = Account::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($user)
            ->post('/journal-entries', [
                'journal_id' => $journal->id,
                'currency_id' => $currency->id,
                'fiscal_year_id' => $fiscalYear->id,
                'accounting_period_id' => $period->id,
                'entry_date' => now()->toDateString(),
                'description' => 'HTTP entry',
                'lines' => [
                    ['account_id' => $cash->id, 'debit' => 250, 'credit' => 0, 'description' => null],
                    ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 250, 'description' => null],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('journal_entries', [
            'organization_id' => $org->id,
            'description' => 'HTTP entry',
            'status' => 'draft',
        ]);
    }

    public function test_http_store_rejects_unbalanced_entry(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);

        $journal = Journal::factory()->create(['organization_id' => $org->id]);
        $currency = Currency::factory()->create(['code' => 'USD']);
        $fiscalYear = FiscalYear::factory()->create(['organization_id' => $org->id]);
        $period = AccountingPeriod::factory()->create([
            'organization_id' => $org->id,
            'fiscal_year_id' => $fiscalYear->id,
        ]);
        $cash = Account::factory()->create(['organization_id' => $org->id]);
        $revenue = Account::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($user)
            ->post('/journal-entries', [
                'journal_id' => $journal->id,
                'currency_id' => $currency->id,
                'fiscal_year_id' => $fiscalYear->id,
                'accounting_period_id' => $period->id,
                'entry_date' => now()->toDateString(),
                'description' => 'Unbalanced HTTP entry',
                'lines' => [
                    ['account_id' => $cash->id, 'debit' => 500, 'credit' => 0, 'description' => null],
                    ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 400, 'description' => null],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('journal_entries', [
            'description' => 'Unbalanced HTTP entry',
        ]);
    }
}
