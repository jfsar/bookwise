<?php

namespace Tests\Feature;

use App\Enums\AccountingPeriodStatus;
use App\Enums\FiscalYearStatus;
use App\Enums\VendorBillStatus;
use App\Enums\VendorDebitNoteStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TaxCode;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorBillLine;
use App\Models\VendorDebitNote;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VendorDebitNoteTest extends TestCase
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

    private function debitNoteOrganization(): array
    {
        $org = Organization::factory()->create();
        $payable = Account::factory()->create(['organization_id' => $org->id]);
        $expense = Account::factory()->create(['organization_id' => $org->id]);
        $taxCode = TaxCode::factory()->create([
            'organization_id' => $org->id,
            'is_active' => true,
        ]);
        $vendor = Vendor::factory()->create([
            'organization_id' => $org->id,
            'payable_account_id' => $payable->id,
        ]);

        return [$org, $payable, $expense, $taxCode, $vendor, $org->base_currency_id];
    }

    private function postingSetup(): array
    {
        [$org, $payable, $expense, $taxCode, $vendor, $currencyId] = $this->debitNoteOrganization();

        $fiscalYear = FiscalYear::factory()->create([
            'organization_id' => $org->id,
            'start_date' => today()->startOfYear()->toDateString(),
            'end_date' => today()->endOfYear()->toDateString(),
            'status' => FiscalYearStatus::Open,
        ]);
        $period = AccountingPeriod::factory()->create([
            'organization_id' => $org->id,
            'fiscal_year_id' => $fiscalYear->id,
            'start_date' => today()->startOfMonth()->toDateString(),
            'end_date' => today()->endOfMonth()->toDateString(),
            'status' => AccountingPeriodStatus::Open,
        ]);
        Journal::factory()->create([
            'organization_id' => $org->id,
            'code' => 'GJ',
            'is_active' => true,
        ]);

        return [$org, $payable, $expense, $taxCode, $vendor, $currencyId, $fiscalYear, $period];
    }

    private function postedBill(
        Organization $org,
        User $creator,
        Vendor $vendor,
        Account $expense,
        ?TaxCode $taxCode,
        int $currencyId,
        float $subtotal = 100,
        float $tax = 0,
    ): VendorBill {
        $bill = VendorBill::factory()->posted()->create([
            'organization_id' => $org->id,
            'vendor_id' => $vendor->id,
            'currency_id' => $currencyId,
            'base_currency_id' => $org->base_currency_id,
            'bill_date' => today()->toDateString(),
            'due_date' => today()->addDays(15)->toDateString(),
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'tax_amount' => $tax,
            'total_amount' => round($subtotal + $tax, 4),
            'paid_amount' => 0,
            'balance_due' => round($subtotal + $tax, 4),
            'created_by' => $creator->id,
        ]);

        VendorBillLine::create([
            'organization_id' => $org->id,
            'bill_id' => $bill->id,
            'line_number' => 1,
            'description' => 'Test goods',
            'quantity' => 1,
            'unit_price' => $subtotal,
            'discount_amount' => 0,
            'tax_amount' => $tax,
            'line_total' => round($subtotal + $tax, 4),
            'expense_account_id' => $expense->id,
            'tax_code_id' => $taxCode?->id,
            'currency_id' => $currencyId,
        ]);

        return $bill;
    }

    private function storePayload(int $vendorId, int $billId, float $subtotal = 100, float $tax = 0): array
    {
        return [
            'vendor_id' => $vendorId,
            'bill_id' => $billId,
            'date' => today()->toDateString(),
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'reason' => 'Vendor credit received',
            'idempotency_key' => 'dbn-'.fake()->uuid(),
        ];
    }

    // ─── Index Tests ──────────────────────────────────────────────────────

    public function test_index_requires_authentication(): void
    {
        $this->get('/vendor-debit-notes')->assertRedirect(route('login'));
    }

    public function test_index_forbids_user_without_permission(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $this->attachRoleWithPermission($user, $org, []);

        $this->actingAs($user)
            ->get('/vendor-debit-notes')
            ->assertStatus(403);
    }

    public function test_index_allows_user_with_permission(): void
    {
        $user = User::factory()->create();
        [$org] = $this->debitNoteOrganization();
        $this->attachRoleWithPermission($user, $org, 'view-vendor-debit-notes');

        $this->actingAs($user)
            ->get('/vendor-debit-notes')
            ->assertOk();
    }

    // ─── Store Tests ──────────────────────────────────────────────────────

    public function test_store_forbids_user_without_permission(): void
    {
        $user = User::factory()->create();
        [$org, $payable, $expense, $taxCode, $vendor, $currencyId] = $this->debitNoteOrganization();
        $bill = $this->postedBill($org, $user, $vendor, $expense, $taxCode, $currencyId);
        $this->attachRoleWithPermission($user, $org, []);

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/vendor-debit-notes', $this->storePayload($vendor->id, $bill->id))
            ->assertStatus(403);
    }

    public function test_store_creates_draft_debit_note_with_audit(): void
    {
        $user = User::factory()->create();
        [$org, $payable, $expense, $taxCode, $vendor, $currencyId] = $this->debitNoteOrganization();
        $bill = $this->postedBill($org, $user, $vendor, $expense, $taxCode, $currencyId, 100, 12);
        $this->attachRoleWithPermission($user, $org, 'create-vendor-debit-notes');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/vendor-debit-notes', $this->storePayload($vendor->id, $bill->id, 100, 12))
            ->assertRedirect()
            ->assertSessionHas('success');

        $debitNote = VendorDebitNote::query()
            ->where('organization_id', $org->id)
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        $this->assertSame(VendorDebitNoteStatus::Draft, $debitNote->status);
        $this->assertStringStartsWith('DBN-', $debitNote->debit_note_number);
        $this->assertSame($bill->id, $debitNote->bill_id);
        $this->assertSame(100.0, (float) $debitNote->subtotal);
        $this->assertSame(12.0, (float) $debitNote->tax_amount);
        $this->assertSame(112.0, (float) $debitNote->total_amount);
        $this->assertNull($debitNote->journal_entry_id);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'vendor-debit-note.created',
            'auditable_type' => VendorDebitNote::class,
            'auditable_id' => $debitNote->id,
        ]);
    }

    public function test_store_requires_fields(): void
    {
        $user = User::factory()->create();
        [$org] = $this->debitNoteOrganization();
        $this->attachRoleWithPermission($user, $org, 'create-vendor-debit-notes');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/vendor-debit-notes', [])
            ->assertRedirect()
            ->assertSessionHasErrors(['vendor_id', 'bill_id', 'date', 'subtotal', 'tax_amount', 'reason']);
    }

    public function test_store_rejects_cross_organization_vendor(): void
    {
        $user = User::factory()->create();
        [$org, $payable, $expense, $taxCode, $vendor, $currencyId] = $this->debitNoteOrganization();
        $otherOrg = Organization::factory()->create();
        $otherPayable = Account::factory()->create(['organization_id' => $otherOrg->id]);
        $otherVendor = Vendor::factory()->create([
            'organization_id' => $otherOrg->id,
            'payable_account_id' => $otherPayable->id,
        ]);
        $this->attachRoleWithPermission($user, $org, 'create-vendor-debit-notes');

        $bill = $this->postedBill($org, $user, $vendor, $expense, $taxCode, $currencyId);

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/vendor-debit-notes', $this->storePayload($otherVendor->id, $bill->id))
            ->assertRedirect()
            ->assertSessionHas('error', 'The vendor must belong to the same organization.');
    }

    public function test_store_rejects_inactive_vendor(): void
    {
        $user = User::factory()->create();
        [$org, $payable, $expense, $taxCode, $vendor, $currencyId] = $this->debitNoteOrganization();
        $vendor->update(['status' => 'inactive']);
        $this->attachRoleWithPermission($user, $org, 'create-vendor-debit-notes');

        $bill = $this->postedBill($org, $user, $vendor, $expense, $taxCode, $currencyId);

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/vendor-debit-notes', $this->storePayload($vendor->id, $bill->id))
            ->assertRedirect()
            ->assertSessionHas('error', 'The vendor must be active.');
    }

    public function test_store_rejects_cross_organization_bill(): void
    {
        $user = User::factory()->create();
        [$org, $payable, $expense, $taxCode, $vendor, $currencyId] = $this->debitNoteOrganization();
        $otherOrg = Organization::factory()->create();
        $otherPayable = Account::factory()->create(['organization_id' => $otherOrg->id]);
        $otherExpense = Account::factory()->create(['organization_id' => $otherOrg->id]);
        $otherVendor = Vendor::factory()->create([
            'organization_id' => $otherOrg->id,
            'payable_account_id' => $otherPayable->id,
        ]);
        $otherBill = $this->postedBill($otherOrg, $user, $otherVendor, $otherExpense, null, $otherOrg->base_currency_id);
        $this->attachRoleWithPermission($user, $org, 'create-vendor-debit-notes');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/vendor-debit-notes', $this->storePayload($vendor->id, $otherBill->id))
            ->assertRedirect()
            ->assertSessionHas('error', 'The bill must belong to the same organization.');
    }

    public function test_store_rejects_bill_of_another_vendor(): void
    {
        $user = User::factory()->create();
        [$org, $payable, $expense, $taxCode, $vendor, $currencyId] = $this->debitNoteOrganization();
        $otherVendor = Vendor::factory()->create([
            'organization_id' => $org->id,
            'payable_account_id' => Account::factory()->create(['organization_id' => $org->id])->id,
        ]);
        $bill = $this->postedBill($org, $user, $otherVendor, $expense, $taxCode, $currencyId);
        $this->attachRoleWithPermission($user, $org, 'create-vendor-debit-notes');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/vendor-debit-notes', $this->storePayload($vendor->id, $bill->id))
            ->assertRedirect()
            ->assertSessionHas('error', 'The bill must belong to the same vendor.');
    }

    public function test_store_rejects_unposted_bill(): void
    {
        $user = User::factory()->create();
        [$org, $payable, $expense, $taxCode, $vendor, $currencyId] = $this->debitNoteOrganization();
        $bill = VendorBill::factory()->create([
            'organization_id' => $org->id,
            'vendor_id' => $vendor->id,
            'created_by' => $user->id,
            'currency_id' => $currencyId,
            'base_currency_id' => $org->base_currency_id,
            'status' => VendorBillStatus::Draft,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
            'balance_due' => 100,
        ]);
        $this->attachRoleWithPermission($user, $org, 'create-vendor-debit-notes');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/vendor-debit-notes', $this->storePayload($vendor->id, $bill->id))
            ->assertRedirect()
            ->assertSessionHas('error', 'Only posted bills can be debited.');
    }

    public function test_store_rejects_debit_exceeding_outstanding_balance(): void
    {
        $user = User::factory()->create();
        [$org, $payable, $expense, $taxCode, $vendor, $currencyId] = $this->debitNoteOrganization();
        $bill = $this->postedBill($org, $user, $vendor, $expense, $taxCode, $currencyId, 100, 0);
        $this->attachRoleWithPermission($user, $org, 'create-vendor-debit-notes');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/vendor-debit-notes', $this->storePayload($vendor->id, $bill->id, 150))
            ->assertRedirect()
            ->assertSessionHas('error', 'The debit note total must not exceed the outstanding balance of the bill.');
    }

    public function test_store_is_idempotent_via_idempotency_key(): void
    {
        $user = User::factory()->create();
        [$org, $payable, $expense, $taxCode, $vendor, $currencyId] = $this->debitNoteOrganization();
        $bill = $this->postedBill($org, $user, $vendor, $expense, $taxCode, $currencyId);
        $this->attachRoleWithPermission($user, $org, 'create-vendor-debit-notes');

        $this->actingAs($user);
        $this->confirmPassword();

        $payload = $this->storePayload($vendor->id, $bill->id);
        $payload['idempotency_key'] = 'dbn-duplicate-key';

        $this->post('/vendor-debit-notes', $payload)->assertSessionHas('success');
        $this->post('/vendor-debit-notes', $payload)->assertSessionHas('success');

        $this->assertSame(1, VendorDebitNote::forOrganization($org)->count());
    }

    // ─── Posting Tests ────────────────────────────────────────────────────

    public function test_post_posts_debit_note_to_ledger(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, $payable, $expense, $taxCode, $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-vendor-debit-notes']);
        $this->attachRoleWithPermission($poster, $org, 'approve-vendor-debit-notes');

        $bill = $this->postedBill($org, $creator, $vendor, $expense, $taxCode, $currencyId, 100, 12);

        $this->actingAs($creator);
        $this->confirmPassword();
        $this->post('/vendor-debit-notes', $this->storePayload($vendor->id, $bill->id, 100, 12))
            ->assertSessionHas('success');

        $debitNote = VendorDebitNote::forOrganization($org)->firstOrFail();

        $this->actingAs($poster)
            ->post("/vendor-debit-notes/{$debitNote->id}/post")
            ->assertRedirect()
            ->assertSessionHas('success', 'Debit note posted to ledger.');

        $debitNote->refresh();
        $this->assertSame(VendorDebitNoteStatus::Posted, $debitNote->status);
        $this->assertNotNull($debitNote->journal_entry_id);

        $entry = $debitNote->journalEntry->load('lines');
        $this->assertTrue($entry->isBalanced());

        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $payable->id,
            'debit' => 112,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $expense->id,
            'debit' => 0,
            'credit' => 100,
        ]);
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $taxCode->tax_account_id,
            'debit' => 0,
            'credit' => 12,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'vendor-debit-note.posted',
            'auditable_type' => VendorDebitNote::class,
            'auditable_id' => $debitNote->id,
        ]);
    }

    public function test_post_forbids_creator_from_posting_own_debit_note(): void
    {
        $user = User::factory()->create();
        [$org, $payable, $expense, $taxCode, $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, ['create-vendor-debit-notes', 'approve-vendor-debit-notes']);

        $bill = $this->postedBill($org, $user, $vendor, $expense, $taxCode, $currencyId);

        $debitNote = VendorDebitNote::factory()->create([
            'organization_id' => $org->id,
            'vendor_id' => $vendor->id,
            'bill_id' => $bill->id,
            'created_by' => $user->id,
            'currency_id' => $currencyId,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
        ]);

        $this->actingAs($user)
            ->post("/vendor-debit-notes/{$debitNote->id}/post")
            ->assertRedirect()
            ->assertSessionHas('error', 'Maker-checker: The final poster cannot be the same person who created the debit note.');

        $this->assertSame(VendorDebitNoteStatus::Draft, $debitNote->fresh()->status);
    }

    public function test_post_forbids_user_without_permission(): void
    {
        $creator = User::factory()->create();
        $user = User::factory()->create();
        [$org, $payable, $expense, $taxCode, $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, 'create-vendor-debit-notes');
        $this->attachRoleWithPermission($user, $org, []);

        $bill = $this->postedBill($org, $creator, $vendor, $expense, $taxCode, $currencyId);

        $debitNote = VendorDebitNote::factory()->create([
            'organization_id' => $org->id,
            'vendor_id' => $vendor->id,
            'bill_id' => $bill->id,
            'created_by' => $creator->id,
            'currency_id' => $currencyId,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
        ]);

        $this->actingAs($user)
            ->post("/vendor-debit-notes/{$debitNote->id}/post")
            ->assertStatus(403);
    }

    public function test_post_returns_404_for_cross_organization_debit_note(): void
    {
        $creator = User::factory()->create();
        [$orgA, $payableA, $expenseA, $taxCodeA, $vendorA, $currencyIdA] = $this->postingSetup();
        $orgB = Organization::factory()->create();
        $this->attachRoleWithPermission($creator, $orgA, 'create-vendor-debit-notes');
        $this->attachRoleWithPermission($creator, $orgB, 'approve-vendor-debit-notes');

        $bill = $this->postedBill($orgA, $creator, $vendorA, $expenseA, $taxCodeA, $currencyIdA);

        $debitNote = VendorDebitNote::factory()->create([
            'organization_id' => $orgA->id,
            'vendor_id' => $vendorA->id,
            'bill_id' => $bill->id,
            'created_by' => $creator->id,
            'currency_id' => $currencyIdA,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
        ]);

        session(['current_organization_id' => $orgB->id]);

        $this->actingAs($creator)
            ->post("/vendor-debit-notes/{$debitNote->id}/post")
            ->assertStatus(404);
    }

    public function test_post_rejects_closed_period(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, $payable, $expense, $taxCode, $vendor, $currencyId, , $period] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-vendor-debit-notes']);
        $this->attachRoleWithPermission($poster, $org, 'approve-vendor-debit-notes');

        $period->update(['status' => AccountingPeriodStatus::Closed]);

        $bill = $this->postedBill($org, $creator, $vendor, $expense, $taxCode, $currencyId);

        $debitNote = VendorDebitNote::factory()->create([
            'organization_id' => $org->id,
            'vendor_id' => $vendor->id,
            'bill_id' => $bill->id,
            'created_by' => $creator->id,
            'currency_id' => $currencyId,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
        ]);

        $this->actingAs($poster)
            ->post("/vendor-debit-notes/{$debitNote->id}/post")
            ->assertRedirect()
            ->assertSessionHas('error', 'No open accounting period covers the debit note date.');

        $this->assertSame(VendorDebitNoteStatus::Draft, $debitNote->fresh()->status);
    }

    public function test_post_rejects_debit_exceeding_outstanding_balance(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, $payable, $expense, $taxCode, $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-vendor-debit-notes']);
        $this->attachRoleWithPermission($poster, $org, 'approve-vendor-debit-notes');

        $bill = $this->postedBill($org, $creator, $vendor, $expense, $taxCode, $currencyId, 100, 0);

        $debitNote = VendorDebitNote::factory()->create([
            'organization_id' => $org->id,
            'vendor_id' => $vendor->id,
            'bill_id' => $bill->id,
            'created_by' => $creator->id,
            'currency_id' => $currencyId,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
        ]);

        $this->actingAs($poster)
            ->post("/vendor-debit-notes/{$debitNote->id}/post")
            ->assertSessionHas('success');

        $second = VendorDebitNote::factory()->create([
            'organization_id' => $org->id,
            'vendor_id' => $vendor->id,
            'bill_id' => $bill->id,
            'created_by' => $creator->id,
            'currency_id' => $currencyId,
            'subtotal' => 10,
            'tax_amount' => 0,
            'total_amount' => 10,
        ]);

        $this->actingAs($poster)
            ->post("/vendor-debit-notes/{$second->id}/post")
            ->assertRedirect()
            ->assertSessionHas('error', 'The debit note total must not exceed the outstanding balance of the bill.');

        $this->assertSame(VendorDebitNoteStatus::Draft, $second->fresh()->status);
    }

    public function test_post_returns_generic_error_on_query_exception(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, $payable, $expense, $taxCode, $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-vendor-debit-notes']);
        $this->attachRoleWithPermission($poster, $org, 'approve-vendor-debit-notes');

        $bill = $this->postedBill($org, $creator, $vendor, $expense, $taxCode, $currencyId);

        $debitNote = VendorDebitNote::factory()->create([
            'organization_id' => $org->id,
            'vendor_id' => $vendor->id,
            'bill_id' => $bill->id,
            'created_by' => $creator->id,
            'currency_id' => $currencyId,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
        ]);

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn(['description']);
        });

        $this->actingAs($poster)
            ->post("/vendor-debit-notes/{$debitNote->id}/post")
            ->assertRedirect()
            ->assertSessionHas('error', 'Unable to post the debit note. Please try again.');
    }

    // ─── Void Tests ───────────────────────────────────────────────────────

    public function test_void_draft_debit_note_with_reason(): void
    {
        $user = User::factory()->create();
        [$org, $payable, $expense, $taxCode, $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, 'void-vendor-debit-notes');

        $bill = $this->postedBill($org, $user, $vendor, $expense, $taxCode, $currencyId);

        $debitNote = VendorDebitNote::factory()->create([
            'organization_id' => $org->id,
            'vendor_id' => $vendor->id,
            'bill_id' => $bill->id,
            'created_by' => $user->id,
            'currency_id' => $currencyId,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
        ]);

        $this->actingAs($user)
            ->post("/vendor-debit-notes/{$debitNote->id}/void", ['reason' => 'Created in error'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Debit note voided.');

        $debitNote->refresh();
        $this->assertSame(VendorDebitNoteStatus::Voided, $debitNote->status);
        $this->assertSame('Created in error', $debitNote->void_reason);
        $this->assertNotNull($debitNote->voided_at);
        $this->assertSame($user->id, $debitNote->voided_by);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'vendor-debit-note.voided',
            'auditable_type' => VendorDebitNote::class,
            'auditable_id' => $debitNote->id,
        ]);
    }

    public function test_void_requires_reason(): void
    {
        $user = User::factory()->create();
        [$org, $payable, $expense, $taxCode, $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, 'void-vendor-debit-notes');

        $bill = $this->postedBill($org, $user, $vendor, $expense, $taxCode, $currencyId);

        $debitNote = VendorDebitNote::factory()->create([
            'organization_id' => $org->id,
            'vendor_id' => $vendor->id,
            'bill_id' => $bill->id,
            'created_by' => $user->id,
            'currency_id' => $currencyId,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
        ]);

        $this->actingAs($user)
            ->post("/vendor-debit-notes/{$debitNote->id}/void", [])
            ->assertRedirect()
            ->assertSessionHasErrors(['reason']);
    }

    public function test_void_forbids_user_without_permission(): void
    {
        $user = User::factory()->create();
        [$org, $payable, $expense, $taxCode, $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, []);

        $bill = $this->postedBill($org, $user, $vendor, $expense, $taxCode, $currencyId);

        $debitNote = VendorDebitNote::factory()->create([
            'organization_id' => $org->id,
            'vendor_id' => $vendor->id,
            'bill_id' => $bill->id,
            'created_by' => $user->id,
            'currency_id' => $currencyId,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
        ]);

        $this->actingAs($user)
            ->post("/vendor-debit-notes/{$debitNote->id}/void", ['reason' => 'test'])
            ->assertStatus(403);
    }

    public function test_void_rejects_non_draft_debit_note(): void
    {
        $user = User::factory()->create();
        [$org, $payable, $expense, $taxCode, $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, 'void-vendor-debit-notes');

        $bill = $this->postedBill($org, $user, $vendor, $expense, $taxCode, $currencyId);

        $debitNote = VendorDebitNote::factory()->posted()->create([
            'organization_id' => $org->id,
            'vendor_id' => $vendor->id,
            'bill_id' => $bill->id,
            'created_by' => $user->id,
            'currency_id' => $currencyId,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
        ]);

        $this->actingAs($user)
            ->post("/vendor-debit-notes/{$debitNote->id}/void", ['reason' => 'test'])
            ->assertRedirect()
            ->assertSessionHas('error', 'Only draft debit notes can be voided.');

        $this->assertSame(VendorDebitNoteStatus::Posted, $debitNote->fresh()->status);
    }
}
