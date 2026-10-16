<?php

namespace Tests\Feature;

use App\Actions\JournalEntry\ApproveJournalEntry;
use App\Actions\JournalEntry\CreateJournalEntry;
use App\Actions\JournalEntry\SubmitJournalEntry;
use App\Enums\ApprovalMode;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\ApprovalRule;
use App\Models\ApprovalWorkflow;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ApprovalDecisionMade;
use App\Notifications\ApprovalRequestCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApprovalNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function member(User $user, Organization $organization, ?Role $role = null): User
    {
        $user->organizations()->attach($organization->id, ['membership_status' => 'active']);

        if ($role !== null) {
            $user->roles()->attach($role->id, ['organization_id' => $organization->id]);
        }

        return $user;
    }

    private function approverRole(Organization $organization, string $permission = 'approve-journal-entries'): Role
    {
        $role = Role::create([
            'name' => 'Approver',
            'slug' => 'approver-'.Str::lower(Str::random(10)),
            'is_global' => false,
        ]);

        $role->permissions()->attach(Permission::firstOrCreate(['name' => $permission])->id);

        return $role;
    }

    private function workflow(Organization $organization, Role $role): ApprovalWorkflow
    {
        $workflow = ApprovalWorkflow::create([
            'organization_id' => $organization->id,
            'code' => 'JE-'.Str::upper(Str::random(6)),
            'name' => 'Journal entry approval',
            'transaction_type' => 'journal-entry',
            'is_active' => true,
        ]);

        ApprovalRule::create([
            'organization_id' => $organization->id,
            'workflow_id' => $workflow->id,
            'priority' => 0,
            'transaction_type' => 'journal-entry',
            'is_active' => true,
        ]);

        $workflow->steps()->create([
            'organization_id' => $organization->id,
            'sequence' => 1,
            'name' => 'One approver',
            'required_role_id' => $role->id,
            'approval_mode' => ApprovalMode::Sequential,
            'minimum_approvals' => 1,
        ]);

        return $workflow;
    }

    private function entry(Organization $organization, User $creator): JournalEntry
    {
        $journal = Journal::factory()->create(['organization_id' => $organization->id]);
        $currency = Currency::factory()->create();
        $fiscalYear = FiscalYear::factory()->create(['organization_id' => $organization->id]);
        $period = AccountingPeriod::factory()->create([
            'organization_id' => $organization->id,
            'fiscal_year_id' => $fiscalYear->id,
        ]);
        $cash = Account::factory()->create(['organization_id' => $organization->id]);
        $revenue = Account::factory()->create(['organization_id' => $organization->id]);

        return (new CreateJournalEntry)->handle([
            'journal_id' => $journal->id,
            'currency_id' => $currency->id,
            'fiscal_year_id' => $fiscalYear->id,
            'accounting_period_id' => $period->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Notification test entry',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 500, 'credit' => 0, 'description' => null],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 500, 'description' => null],
            ],
        ], $organization, $creator);
    }

    public function test_submitting_an_entry_notifies_the_role_holders_of_the_open_step(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create();
        $role = $this->approverRole($organization);
        $this->workflow($organization, $role);

        $submitter = $this->member(User::factory()->create(), $organization);
        $approver = $this->member(User::factory()->create(), $organization, $role);
        $bystander = $this->member(User::factory()->create(), $organization);

        $entry = $this->entry($organization, $submitter);

        (new SubmitJournalEntry)->handle($entry, $submitter);

        Notification::assertSentTo($approver, ApprovalRequestCreated::class);
        Notification::assertNotSentTo($bystander, ApprovalRequestCreated::class);
    }

    public function test_approval_request_notification_carries_the_organization_id(): void
    {
        $organization = Organization::factory()->create();
        $role = $this->approverRole($organization);
        $this->workflow($organization, $role);

        $submitter = $this->member(User::factory()->create(), $organization);
        $approver = $this->member(User::factory()->create(), $organization, $role);

        (new SubmitJournalEntry)->handle($this->entry($organization, $submitter), $submitter);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $approver->id,
            'type' => ApprovalRequestCreated::class,
            'organization_id' => $organization->id,
        ]);
    }

    public function test_deciding_an_approval_request_notifies_the_requester(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create();
        $role = $this->approverRole($organization);
        $this->workflow($organization, $role);

        $submitter = $this->member(User::factory()->create(), $organization);
        $approver = $this->member(User::factory()->create(), $organization, $role);

        $entry = $this->entry($organization, $submitter);
        (new SubmitJournalEntry)->handle($entry, $submitter);

        (new ApproveJournalEntry)->handle($entry, $approver);

        Notification::assertSentTo($submitter, ApprovalDecisionMade::class);
    }

    public function test_decision_notification_is_scoped_to_the_requesting_organization(): void
    {
        $organization = Organization::factory()->create();
        $role = $this->approverRole($organization);
        $this->workflow($organization, $role);

        $submitter = $this->member(User::factory()->create(), $organization);
        $approver = $this->member(User::factory()->create(), $organization, $role);

        $entry = $this->entry($organization, $submitter);
        (new SubmitJournalEntry)->handle($entry, $submitter);
        (new ApproveJournalEntry)->handle($entry, $approver);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $submitter->id,
            'type' => ApprovalDecisionMade::class,
            'organization_id' => $organization->id,
        ]);
    }

    public function test_no_approval_notifications_without_a_governing_workflow(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create();
        $submitter = $this->member(User::factory()->create(), $organization);

        $entry = $this->entry($organization, $submitter);

        (new SubmitJournalEntry)->handle($entry, $submitter);

        Notification::assertNothingSent();
    }
}
