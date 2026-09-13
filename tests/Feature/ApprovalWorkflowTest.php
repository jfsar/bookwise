<?php

namespace Tests\Feature;

use App\Actions\JournalEntry\ApproveJournalEntry;
use App\Actions\JournalEntry\CancelJournalEntry;
use App\Actions\JournalEntry\CreateJournalEntry;
use App\Actions\JournalEntry\RejectJournalEntry;
use App\Actions\JournalEntry\SubmitJournalEntry;
use App\Enums\ApprovalActionType;
use App\Enums\ApprovalMode;
use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestStepStatus;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\ApprovalAction;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\ApprovalWorkflow;
use App\Models\AuditLog;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
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
        $permissionModel = Permission::firstOrCreate(['name' => $permission]);

        $role = Role::create([
            'name' => 'Approver',
            'slug' => 'approver-'.Str::lower(Str::random(10)),
            'is_global' => false,
        ]);

        $role->permissions()->attach($permissionModel->id);

        return $role;
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    private function workflow(Organization $organization, array $steps): ApprovalWorkflow
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

        foreach ($steps as $index => $step) {
            $workflow->steps()->create([
                'organization_id' => $organization->id,
                'sequence' => $index + 1,
                'name' => $step['name'],
                'required_role_id' => isset($step['role']) ? $step['role']->id : null,
                'required_permission_id' => Permission::firstOrCreate(['name' => $step['permission'] ?? 'approve-journal-entries'])->id,
                'minimum_amount' => $step['minimum_amount'] ?? null,
                'maximum_amount' => $step['maximum_amount'] ?? null,
                'approval_mode' => $step['mode'] ?? ApprovalMode::Sequential,
                'minimum_approvals' => $step['minimum_approvals'] ?? 1,
            ]);
        }

        return $workflow;
    }

    private function entry(Organization $organization, User $creator, int $amount = 500): JournalEntry
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
            'description' => 'Approval test entry',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => $amount, 'credit' => 0, 'description' => null],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => $amount, 'description' => null],
            ],
        ], $organization, $creator);
    }

    public function test_resolve_selects_matching_amount_tier_by_priority(): void
    {
        $org = Organization::factory()->create();

        $routine = ApprovalWorkflow::create([
            'organization_id' => $org->id,
            'code' => 'JE-ROUTINE',
            'name' => 'Routine journals',
            'transaction_type' => 'journal-entry',
            'is_active' => true,
        ]);
        ApprovalRule::create([
            'organization_id' => $org->id,
            'workflow_id' => $routine->id,
            'priority' => 0,
            'transaction_type' => 'journal-entry',
            'minimum_amount' => 0,
            'maximum_amount' => 100000,
            'is_active' => true,
        ]);

        $large = ApprovalWorkflow::create([
            'organization_id' => $org->id,
            'code' => 'JE-LARGE',
            'name' => 'Large journals',
            'transaction_type' => 'journal-entry',
            'is_active' => true,
        ]);
        ApprovalRule::create([
            'organization_id' => $org->id,
            'workflow_id' => $large->id,
            'priority' => 1,
            'transaction_type' => 'journal-entry',
            'minimum_amount' => 500000,
            'is_active' => true,
        ]);

        $service = app(ApprovalWorkflowService::class);

        $this->assertSame($routine->id, $service->resolve($org, 'journal-entry', 25000)?->id);
        $this->assertSame($large->id, $service->resolve($org, 'journal-entry', 750000)?->id);
        $this->assertNull($service->resolve($org, 'journal-entry', 250000));
        $this->assertNull($service->resolve($org, 'bank-import', 750000));
    }

    public function test_resolve_applies_account_scoped_rules(): void
    {
        $org = Organization::factory()->create();
        $cash = Account::factory()->create(['organization_id' => $org->id]);

        $workflow = ApprovalWorkflow::create([
            'organization_id' => $org->id,
            'code' => 'JE-CASH',
            'name' => 'Cash journals',
            'transaction_type' => 'journal-entry',
            'is_active' => true,
        ]);
        ApprovalRule::create([
            'organization_id' => $org->id,
            'workflow_id' => $workflow->id,
            'priority' => 0,
            'transaction_type' => 'journal-entry',
            'account_id' => $cash->id,
            'is_active' => true,
        ]);

        $service = app(ApprovalWorkflowService::class);

        $this->assertNull($service->resolve($org, 'journal-entry', 750000));
        $this->assertSame($workflow->id, $service->resolve($org, 'journal-entry', 750000, [$cash->id])?->id);
    }

    public function test_submit_creates_approval_request_when_workflow_applies(): void
    {
        $org = Organization::factory()->create();
        $workflow = $this->workflow($org, [['name' => 'One approver']]);
        $submitter = $this->member(User::factory()->create(), $org);

        $entry = $this->entry($org, $submitter);

        (new SubmitJournalEntry)->handle($entry, $submitter);

        $this->assertSame('submitted', $entry->fresh()->status->value);

        $request = ApprovalRequest::forSubject($entry)->first();
        $this->assertNotNull($request);
        $this->assertSame(ApprovalRequestStatus::InReview, $request->status);
        $this->assertSame($submitter->id, $request->requested_by);
        $this->assertCount(1, $request->steps);
        $this->assertSame($workflow->id, $request->steps->first()->workflowStep->approval_workflow_id);
        $this->assertSame(ApprovalRequestStepStatus::Pending, $request->steps->first()->status);
    }

    public function test_submit_without_workflow_keeps_direct_approval(): void
    {
        $org = Organization::factory()->create();
        $submitter = $this->member(User::factory()->create(), $org);
        $approver = $this->member(User::factory()->create(), $org, $this->approverRole($org));

        $entry = $this->entry($org, $submitter);

        (new SubmitJournalEntry)->handle($entry, $submitter);

        $this->assertSame(0, ApprovalRequest::count());

        (new ApproveJournalEntry)->handle($entry, $approver);

        $this->assertSame(JournalEntryStatus::Approved, $entry->fresh()->status);
    }

    public function test_maker_checker_blocks_requester_from_approving_request(): void
    {
        $org = Organization::factory()->create();
        $this->workflow($org, [['name' => 'Approver']]);
        $submitter = $this->member(User::factory()->create(), $org);

        $entry = $this->entry($org, $submitter);
        (new SubmitJournalEntry)->handle($entry, $submitter);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Maker-checker: The approver cannot be the same person who submitted the entry.');

        (new ApproveJournalEntry)->handle($entry, $submitter);
    }

    public function test_sequential_approval_resolves_entry_after_all_steps(): void
    {
        $org = Organization::factory()->create();
        $this->workflow($org, [
            ['name' => 'Accountant'],
            ['name' => 'Manager'],
        ]);
        $submitter = $this->member(User::factory()->create(), $org);
        $first = $this->member(User::factory()->create(), $org, $this->approverRole($org));
        $second = $this->member(User::factory()->create(), $org, $this->approverRole($org));

        $entry = $this->entry($org, $submitter);
        (new SubmitJournalEntry)->handle($entry, $submitter);

        (new ApproveJournalEntry)->handle($entry, $first);

        $request = ApprovalRequest::forSubject($entry)->first();
        $this->assertSame(ApprovalRequestStatus::InReview, $request->fresh()->status);
        $this->assertSame(JournalEntryStatus::Submitted, $entry->fresh()->status);
        $this->assertSame(1, ApprovalRequest::forSubject($entry)->first()->fresh(['steps'])->steps->where('status', ApprovalRequestStepStatus::Approved)->count());

        (new ApproveJournalEntry)->handle($entry, $second);

        $request = ApprovalRequest::forSubject($entry)->first();
        $this->assertSame(ApprovalRequestStatus::Approved, $request->fresh()->status);
        $this->assertSame(JournalEntryStatus::Approved, $entry->fresh()->status);
        $this->assertSame($second->id, $entry->fresh()->approved_by);
        $this->assertSame(2, ApprovalAction::count());
    }

    public function test_parallel_step_requires_minimum_distinct_approvers(): void
    {
        $org = Organization::factory()->create();
        $this->workflow($org, [
            ['name' => 'Dual approval', 'mode' => ApprovalMode::Parallel, 'minimum_approvals' => 2],
        ]);
        $submitter = $this->member(User::factory()->create(), $org);
        $first = $this->member(User::factory()->create(), $org, $this->approverRole($org));
        $second = $this->member(User::factory()->create(), $org, $this->approverRole($org));

        $entry = $this->entry($org, $submitter);
        (new SubmitJournalEntry)->handle($entry, $submitter);

        (new ApproveJournalEntry)->handle($entry, $first);

        $request = ApprovalRequest::forSubject($entry)->first();
        $this->assertSame(ApprovalRequestStepStatus::Pending, $request->fresh(['steps'])->steps->first()->status);
        $this->assertSame(1, $request->fresh(['steps'])->steps->first()->approved_count);
        $this->assertSame(JournalEntryStatus::Submitted, $entry->fresh()->status);

        (new ApproveJournalEntry)->handle($entry, $second);

        $request = ApprovalRequest::forSubject($entry)->first();
        $this->assertSame(ApprovalRequestStatus::Approved, $request->fresh()->status);
        $this->assertSame(ApprovalRequestStepStatus::Approved, $request->fresh(['steps'])->steps->first()->status);
        $this->assertSame(JournalEntryStatus::Approved, $entry->fresh()->status);
    }

    public function test_same_user_cannot_approve_parallel_step_twice(): void
    {
        $org = Organization::factory()->create();
        $this->workflow($org, [
            ['name' => 'Dual approval', 'mode' => ApprovalMode::Parallel, 'minimum_approvals' => 2],
        ]);
        $submitter = $this->member(User::factory()->create(), $org);
        $first = $this->member(User::factory()->create(), $org, $this->approverRole($org));

        $entry = $this->entry($org, $submitter);
        (new SubmitJournalEntry)->handle($entry, $submitter);

        (new ApproveJournalEntry)->handle($entry, $first);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The approving user has already approved this step.');

        (new ApproveJournalEntry)->handle($entry, $first);
    }

    public function test_reject_resolves_entry_with_reason_via_request(): void
    {
        $org = Organization::factory()->create();
        $this->workflow($org, [['name' => 'Approver']]);
        $submitter = $this->member(User::factory()->create(), $org);
        $rejecter = $this->member(User::factory()->create(), $org, $this->approverRole($org));

        $entry = $this->entry($org, $submitter);
        (new SubmitJournalEntry)->handle($entry, $submitter);

        (new RejectJournalEntry)->handle($entry, $rejecter, 'Missing support');

        $refreshed = $entry->fresh();
        $this->assertSame(JournalEntryStatus::Rejected, $refreshed->status);
        $this->assertSame('Missing support', $refreshed->rejection_reason);

        $request = ApprovalRequest::forSubject($entry)->first();
        $this->assertSame(ApprovalRequestStatus::Rejected, $request->fresh()->status);
        $this->assertSame('Missing support', $request->fresh()->resolution_note);
        $this->assertSame(1, ApprovalAction::where('action', ApprovalActionType::Reject)->count());
    }

    public function test_reject_requires_a_reason(): void
    {
        $org = Organization::factory()->create();
        $this->workflow($org, [['name' => 'Approver']]);
        $submitter = $this->member(User::factory()->create(), $org);
        $rejecter = $this->member(User::factory()->create(), $org, $this->approverRole($org));

        $entry = $this->entry($org, $submitter);
        (new SubmitJournalEntry)->handle($entry, $submitter);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Rejection requires a reason.');

        (new RejectJournalEntry)->handle($entry, $rejecter, '');
    }

    public function test_approver_without_required_permission_is_denied(): void
    {
        $org = Organization::factory()->create();
        $this->workflow($org, [['name' => 'Approver']]);
        $submitter = $this->member(User::factory()->create(), $org);
        $outsider = $this->member(User::factory()->create(), $org);

        $entry = $this->entry($org, $submitter);
        (new SubmitJournalEntry)->handle($entry, $submitter);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The approving user does not have the required permission for this approval step.');

        (new ApproveJournalEntry)->handle($entry, $outsider);
    }

    public function test_deny_approval_for_user_from_another_organization(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $this->workflow($org, [['name' => 'Approver']]);
        $this->workflow($otherOrg, [['name' => 'Other approver']]);
        $submitter = $this->member(User::factory()->create(), $org);
        $foreignApprover = $this->member(User::factory()->create(), $otherOrg, $this->approverRole($otherOrg));

        $entry = $this->entry($org, $submitter);
        (new SubmitJournalEntry)->handle($entry, $submitter);

        $this->assertSame(1, ApprovalRequest::count());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The approving user is not a member of the organization.');

        (new ApproveJournalEntry)->handle($entry, $foreignApprover);
    }

    public function test_requester_withdrawal_abandons_request(): void
    {
        $org = Organization::factory()->create();
        $this->workflow($org, [['name' => 'Approver']]);
        $submitter = $this->member(User::factory()->create(), $org);
        $approver = $this->member(User::factory()->create(), $org, $this->approverRole($org));

        $entry = $this->entry($org, $submitter);
        (new SubmitJournalEntry)->handle($entry, $submitter);

        $request = ApprovalRequest::forSubject($entry)->first();
        app(ApprovalWorkflowService::class)->withdraw($request, $submitter);

        $request = ApprovalRequest::forSubject($entry)->first();
        $this->assertSame(ApprovalRequestStatus::Withdrawn, $request->fresh()->status);
        $this->assertSame(JournalEntryStatus::Submitted, $entry->fresh()->status);

        (new ApproveJournalEntry)->handle($entry, $approver);

        $this->assertSame(JournalEntryStatus::Approved, $entry->fresh()->status);
    }

    public function test_withdraw_is_restricted_to_requester(): void
    {
        $org = Organization::factory()->create();
        $this->workflow($org, [['name' => 'Approver']]);
        $submitter = $this->member(User::factory()->create(), $org);
        $other = $this->member(User::factory()->create(), $org, $this->approverRole($org));

        $entry = $this->entry($org, $submitter);
        (new SubmitJournalEntry)->handle($entry, $submitter);

        $request = ApprovalRequest::forSubject($entry)->first();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Only the requester can withdraw their own approval request.');

        app(ApprovalWorkflowService::class)->withdraw($request, $other);
    }

    public function test_cancel_entry_cancels_open_request(): void
    {
        $org = Organization::factory()->create();
        $this->workflow($org, [['name' => 'Approver']]);
        $submitter = $this->member(User::factory()->create(), $org);

        $entry = $this->entry($org, $submitter);
        (new SubmitJournalEntry)->handle($entry, $submitter);

        (new CancelJournalEntry)->handle($entry, $submitter, 'Incorrect entry');

        $request = ApprovalRequest::forSubject($entry)->first();
        $this->assertSame(ApprovalRequestStatus::Cancelled, $request->fresh()->status);
        $this->assertSame(JournalEntryStatus::Cancelled, $entry->fresh()->status);
    }

    public function test_approval_actions_are_immutable(): void
    {
        $org = Organization::factory()->create();
        $this->workflow($org, [['name' => 'Approver']]);
        $submitter = $this->member(User::factory()->create(), $org);
        $approver = $this->member(User::factory()->create(), $org, $this->approverRole($org));

        $entry = $this->entry($org, $submitter);
        (new SubmitJournalEntry)->handle($entry, $submitter);
        (new ApproveJournalEntry)->handle($entry, $approver);

        $action = ApprovalAction::first();
        $this->assertFalse($action->update(['comment' => 'Hacked']));
        $this->assertFalse($action->delete());
        $this->assertNull($action->fresh()->comment);
    }

    public function test_audit_events_recorded_for_approval_lifecycle(): void
    {
        $org = Organization::factory()->create();
        $this->workflow($org, [['name' => 'Approver']]);
        $submitter = $this->member(User::factory()->create(), $org);
        $approver = $this->member(User::factory()->create(), $org, $this->approverRole($org));

        $entry = $this->entry($org, $submitter);
        (new SubmitJournalEntry)->handle($entry, $submitter);
        (new ApproveJournalEntry)->handle($entry, $approver);

        $actions = AuditLog::pluck('action')->all();

        $this->assertContains('journal-entry.submitted', $actions);
        $this->assertContains('approval-request.created', $actions);
        $this->assertContains('approval-request.step-approved', $actions);
        $this->assertContains('approval-request.approved', $actions);
        $this->assertContains('journal-entry.approved', $actions);
    }

    public function test_http_approve_flow_with_workflow(): void
    {
        $org = Organization::factory()->create();
        $this->workflow($org, [['name' => 'Approver']]);
        $submitter = $this->member(User::factory()->create(), $org, $this->roleWithPermission($org, 'submit-journal-entries'));
        $approver = $this->member(User::factory()->create(), $org, $this->approverRole($org));

        $entry = $this->entry($org, $submitter);

        $this->actingAs($submitter)
            ->post("/journal-entries/{$entry->id}/submit")
            ->assertRedirect();

        $this->assertSame('submitted', $entry->fresh()->status->value);
        $this->assertSame(1, ApprovalRequest::count());

        $this->actingAs($approver)
            ->post("/journal-entries/{$entry->id}/approve")
            ->assertRedirect();

        $this->assertSame(JournalEntryStatus::Approved, $entry->fresh()->status);
    }

    public function test_http_approve_returned_forbidden_without_step_permission(): void
    {
        $org = Organization::factory()->create();
        $this->workflow($org, [['name' => 'Approver']]);
        $submitter = $this->member(User::factory()->create(), $org, $this->roleWithPermission($org, 'submit-journal-entries'));
        $viewer = $this->member(User::factory()->create(), $org);

        $entry = $this->entry($org, $submitter);

        $this->actingAs($submitter)
            ->post("/journal-entries/{$entry->id}/submit")
            ->assertRedirect();

        $this->actingAs($viewer)
            ->post("/journal-entries/{$entry->id}/approve")
            ->assertForbidden();
    }

    private function roleWithPermission(Organization $organization, string $permission): Role
    {
        $permissionModel = Permission::firstOrCreate(['name' => $permission]);

        $role = Role::create([
            'name' => 'Submitter',
            'slug' => 'submitter-'.Str::lower(Str::random(10)),
            'is_global' => false,
        ]);

        $role->permissions()->attach($permissionModel->id);

        return $role;
    }
}
