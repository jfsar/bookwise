<?php

namespace Tests\Feature;

use App\Enums\AccountingPeriodStatus;
use App\Enums\FiscalYearStatus;
use App\Enums\VendorBillStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TaxCode;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorBillLine;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VendorBillTest extends TestCase
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

    private function billOrganization(): array
    {
        $org = Organization::factory()->create();
        $expense = Account::factory()->create(['organization_id' => $org->id]);
        $taxCode = TaxCode::factory()->create([
            'organization_id' => $org->id,
            'is_active' => true,
        ]);
        TaxRate::factory()->create([
            'tax_code_id' => $taxCode->id,
            'organization_id' => $org->id,
            'rate' => 12,
            'rate_type' => 'standard',
            'effective_from' => today()->subYear()->toDateString(),
            'effective_to' => null,
        ]);
        $payable = Account::factory()->create(['organization_id' => $org->id]);
        $vendor = Vendor::factory()->create([
            'organization_id' => $org->id,
            'payable_account_id' => $payable->id,
        ]);

        return [$org, $expense, $taxCode, $vendor, $org->base_currency_id];
    }

    private function storePayload(int $expenseAccountId, int $taxCodeId, int $vendorId, int $currencyId): array
    {
        return [
            'vendor_id' => $vendorId,
            'vendor_reference' => 'RR-0001',
            'bill_date' => today()->toDateString(),
            'due_date' => today()->addDays(15)->toDateString(),
            'currency_id' => $currencyId,
            'exchange_rate' => 1,
            'notes' => 'Test draft bill',
            'idempotency_key' => 'bill-'.fake()->uuid(),
            'lines' => [
                [
                    'description' => 'Office supplies',
                    'quantity' => 2,
                    'unit_price' => 100,
                    'discount_amount' => 0,
                    'expense_account_id' => $expenseAccountId,
                    'tax_code_id' => $taxCodeId,
                ],
            ],
        ];
    }

    public function test_index_requires_authentication(): void
    {
        $this->get('/bills')->assertRedirect(route('login'));
    }

    public function test_index_forbids_user_without_permission(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $this->attachRoleWithPermission($user, $org, []);

        $this->actingAs($user)
            ->get('/bills')
            ->assertStatus(403);
    }

    public function test_index_allows_user_with_permission(): void
    {
        $user = User::factory()->create();
        [$org] = $this->billOrganization();
        $this->attachRoleWithPermission($user, $org, 'view-bills');

        $this->actingAs($user)
            ->get('/bills')
            ->assertOk();
    }

    public function test_store_forbids_user_without_permission(): void
    {
        $user = User::factory()->create();
        [$org, $expense, $taxCode, $vendor, $currencyId] = $this->billOrganization();
        $this->attachRoleWithPermission($user, $org, []);

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/bills', $this->storePayload($expense->id, $taxCode->id, $vendor->id, $currencyId))
            ->assertStatus(403);
    }

    public function test_store_creates_draft_with_totals_and_audit(): void
    {
        $user = User::factory()->create();
        [$org, $expense, $taxCode, $vendor, $currencyId] = $this->billOrganization();
        $this->attachRoleWithPermission($user, $org, 'create-bills');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/bills', $this->storePayload($expense->id, $taxCode->id, $vendor->id, $currencyId))
            ->assertRedirect()
            ->assertSessionHas('success');

        $bill = VendorBill::query()
            ->where('organization_id', $org->id)
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        $this->assertSame(VendorBillStatus::Draft, $bill->status);
        $this->assertSame('RR-0001', $bill->vendor_reference);
        $this->assertSame(200.0, (float) $bill->subtotal);
        $this->assertSame(24.0, (float) $bill->tax_amount);
        $this->assertSame(224.0, (float) $bill->total_amount);
        $this->assertSame(1, $bill->lines()->count());
        $this->assertNull($bill->journal_entry_id);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'vendor-bill.created',
            'auditable_type' => VendorBill::class,
            'auditable_id' => $bill->id,
        ]);
    }

    public function test_store_requires_fields(): void
    {
        $user = User::factory()->create();
        [$org, , $taxCode, $vendor, $currencyId] = $this->billOrganization();
        $this->attachRoleWithPermission($user, $org, 'create-bills');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/bills', [])
            ->assertRedirect()
            ->assertSessionHasErrors(['vendor_id', 'bill_date', 'due_date', 'currency_id', 'lines']);
    }

    public function test_store_rejects_cross_organization_vendor(): void
    {
        $user = User::factory()->create();
        [$org, $expense, $taxCode, , $currencyId] = $this->billOrganization();
        $otherOrg = Organization::factory()->create();
        $otherPayable = Account::factory()->create(['organization_id' => $otherOrg->id]);
        $otherVendor = Vendor::factory()->create([
            'organization_id' => $otherOrg->id,
            'payable_account_id' => $otherPayable->id,
        ]);
        $this->attachRoleWithPermission($user, $org, 'create-bills');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/bills', $this->storePayload($expense->id, $taxCode->id, $otherVendor->id, $currencyId))
            ->assertRedirect()
            ->assertSessionHas('error', 'The vendor must belong to the same organization.');
    }

    public function test_store_rejects_discount_exceeding_extended_amount(): void
    {
        $user = User::factory()->create();
        [$org, $expense, $taxCode, $vendor, $currencyId] = $this->billOrganization();
        $this->attachRoleWithPermission($user, $org, 'create-bills');

        $payload = $this->storePayload($expense->id, $taxCode->id, $vendor->id, $currencyId);
        $payload['lines'][0]['quantity'] = 2;
        $payload['lines'][0]['unit_price'] = 100;
        $payload['lines'][0]['discount_amount'] = 250;

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/bills', $payload)
            ->assertRedirect()
            ->assertSessionHasErrors(['lines.0.discount_amount']);

        $this->assertDatabaseCount('vendor_bills', 0);
    }

    public function test_store_accepts_discount_equal_to_extended_amount(): void
    {
        $user = User::factory()->create();
        [$org, $expense, $taxCode, $vendor, $currencyId] = $this->billOrganization();
        $this->attachRoleWithPermission($user, $org, 'create-bills');

        $payload = $this->storePayload($expense->id, $taxCode->id, $vendor->id, $currencyId);
        $payload['lines'][0]['quantity'] = 1;
        $payload['lines'][0]['unit_price'] = 100;
        $payload['lines'][0]['discount_amount'] = 100;

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/bills', $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vendor_bills', [
            'organization_id' => $org->id,
            'subtotal' => 100,
            'discount_amount' => 100,
            'total_amount' => 0,
        ]);
    }

    // ─── Posting Tests ────────────────────────────────────────────────────

    private function postingSetup(): array
    {
        [$org, $expense, $taxCode, $vendor, $currencyId] = $this->billOrganization();

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

        return [$org, $expense, $taxCode, $vendor, $currencyId, $fiscalYear, $period];
    }

    private function createDraftBill(
        Organization $org,
        User $creator,
        int $expenseAccountId,
        int $vendorId,
        int $currencyId,
    ): VendorBill {
        $bill = VendorBill::factory()->create([
            'organization_id' => $org->id,
            'vendor_id' => $vendorId,
            'created_by' => $creator->id,
            'currency_id' => $currencyId,
            'base_currency_id' => $org->base_currency_id,
            'status' => VendorBillStatus::Draft,
            'bill_date' => today()->toDateString(),
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 100,
            'paid_amount' => 0,
            'balance_due' => 100,
        ]);

        VendorBillLine::create([
            'organization_id' => $org->id,
            'bill_id' => $bill->id,
            'line_number' => 1,
            'description' => 'Test supplies',
            'quantity' => 1,
            'unit_price' => 100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'line_total' => 100,
            'expense_account_id' => $expenseAccountId,
            'currency_id' => $currencyId,
        ]);

        return $bill;
    }

    public function test_post_issues_draft_bill_and_creates_journal_entry(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, $expense, , $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-bills']);
        $this->attachRoleWithPermission($poster, $org, 'approve-bills');

        $bill = $this->createDraftBill($org, $creator, $expense->id, $vendor->id, $currencyId);

        $this->actingAs($poster)
            ->post("/bills/{$bill->id}/post")
            ->assertRedirect()
            ->assertSessionHas('success', 'Bill posted to ledger.');

        $bill->refresh();
        $this->assertSame(VendorBillStatus::Posted, $bill->status);
        $this->assertNotNull($bill->journal_entry_id);
        $this->assertDatabaseHas('journal_entries', [
            'organization_id' => $org->id,
            'status' => 'posted',
        ]);
    }

    public function test_post_creates_expense_debit_and_ap_credit_lines(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, $expense, , $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-bills']);
        $this->attachRoleWithPermission($poster, $org, 'approve-bills');

        $bill = $this->createDraftBill($org, $creator, $expense->id, $vendor->id, $currencyId);

        $this->actingAs($poster)
            ->post("/bills/{$bill->id}/post")
            ->assertSessionHas('success', 'Bill posted to ledger.');

        $entry = JournalEntry::where('organization_id', $org->id)->where('status', 'posted')->firstOrFail();

        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $expense->id,
            'debit' => 100.0,
            'credit' => 0.0,
        ]);

        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $vendor->payable_account_id,
            'debit' => 0.0,
            'credit' => 100.0,
        ]);
    }

    public function test_post_rejects_zero_total_bill(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, $expense, , $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-bills']);
        $this->attachRoleWithPermission($poster, $org, 'approve-bills');

        $bill = VendorBill::factory()->create([
            'organization_id' => $org->id,
            'vendor_id' => $vendor->id,
            'created_by' => $creator->id,
            'currency_id' => $currencyId,
            'base_currency_id' => $org->base_currency_id,
            'status' => VendorBillStatus::Draft,
            'bill_date' => today()->toDateString(),
            'due_date' => today()->addDays(15)->toDateString(),
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
            'balance_due' => 0,
        ]);

        $this->actingAs($poster)
            ->post("/bills/{$bill->id}/post")
            ->assertRedirect()
            ->assertSessionHas('error', 'A bill with a zero total cannot be posted.');

        $this->assertSame(VendorBillStatus::Draft, $bill->fresh()->status);
    }

    public function test_post_forbids_creator_from_posting_own_bill(): void
    {
        $user = User::factory()->create();
        [$org, $expense, , $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, ['create-bills', 'approve-bills']);

        $bill = $this->createDraftBill($org, $user, $expense->id, $vendor->id, $currencyId);

        $this->actingAs($user)
            ->post("/bills/{$bill->id}/post")
            ->assertRedirect()
            ->assertSessionHas('error', 'Maker-checker: The final poster cannot be the same person who created the bill.');

        $this->assertSame(VendorBillStatus::Draft, $bill->fresh()->status);
    }

    public function test_post_forbids_user_without_permission(): void
    {
        $creator = User::factory()->create();
        $user = User::factory()->create();
        [$org, $expense, , $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, 'create-bills');
        $this->attachRoleWithPermission($user, $org, []);

        $bill = $this->createDraftBill($org, $creator, $expense->id, $vendor->id, $currencyId);

        $this->actingAs($user)
            ->post("/bills/{$bill->id}/post")
            ->assertStatus(403);
    }

    public function test_post_returns_404_for_cross_organization_bill(): void
    {
        $creator = User::factory()->create();
        [$orgA, $expenseA, , $vendorA, $currencyIdA] = $this->postingSetup();
        $orgB = Organization::factory()->create();
        $this->attachRoleWithPermission($creator, $orgA, 'create-bills');
        $this->attachRoleWithPermission($creator, $orgB, 'approve-bills');

        $bill = $this->createDraftBill($orgA, $creator, $expenseA->id, $vendorA->id, $currencyIdA);

        session(['current_organization_id' => $orgB->id]);

        $this->actingAs($creator)
            ->post("/bills/{$bill->id}/post")
            ->assertStatus(404);
    }

    public function test_post_returns_error_when_no_organization_selected(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $payable = Account::factory()->create(['organization_id' => $org->id]);
        $vendor = Vendor::factory()->create([
            'organization_id' => $org->id,
            'payable_account_id' => $payable->id,
        ]);

        $bill = VendorBill::factory()->create([
            'organization_id' => $org->id,
            'vendor_id' => $vendor->id,
            'created_by' => User::factory()->create()->id,
            'status' => VendorBillStatus::Draft,
        ]);

        $this->actingAs($user)
            ->post("/bills/{$bill->id}/post")
            ->assertRedirect()
            ->assertSessionHas('error', 'No organization selected.');
    }

    public function test_post_returns_generic_error_on_query_exception(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, $expense, , $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-bills']);
        $this->attachRoleWithPermission($poster, $org, 'approve-bills');

        $bill = $this->createDraftBill($org, $creator, $expense->id, $vendor->id, $currencyId);

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn(['description']);
        });

        $this->actingAs($poster)
            ->post("/bills/{$bill->id}/post")
            ->assertRedirect()
            ->assertSessionHas('error', 'Unable to post the bill. Please try again.');
    }

    // ─── Void Tests ───────────────────────────────────────────────────────

    public function test_void_draft_bill_with_reason(): void
    {
        $user = User::factory()->create();
        [$org, $expense, , $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, 'void-bills');

        $bill = $this->createDraftBill($org, $user, $expense->id, $vendor->id, $currencyId);

        $this->actingAs($user)
            ->post("/bills/{$bill->id}/void", ['reason' => 'Duplicated bill'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Bill voided.');

        $bill->refresh();
        $this->assertSame(VendorBillStatus::Voided, $bill->status);
        $this->assertSame('Duplicated bill', $bill->void_reason);
        $this->assertNotNull($bill->voided_at);
    }

    public function test_void_requires_reason(): void
    {
        $user = User::factory()->create();
        [$org, $expense, , $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, 'void-bills');

        $bill = $this->createDraftBill($org, $user, $expense->id, $vendor->id, $currencyId);

        $this->actingAs($user)
            ->post("/bills/{$bill->id}/void", [])
            ->assertRedirect()
            ->assertSessionHasErrors(['reason']);
    }

    public function test_void_forbids_user_without_permission(): void
    {
        $user = User::factory()->create();
        [$org, $expense, , $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, []);

        $bill = $this->createDraftBill($org, $user, $expense->id, $vendor->id, $currencyId);

        $this->actingAs($user)
            ->post("/bills/{$bill->id}/void", ['reason' => 'test'])
            ->assertStatus(403);
    }

    public function test_void_rejects_paid_bill(): void
    {
        $user = User::factory()->create();
        [$org, $expense, , $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, 'void-bills');

        $bill = VendorBill::factory()->create([
            'organization_id' => $org->id,
            'vendor_id' => $vendor->id,
            'created_by' => User::factory()->create()->id,
            'currency_id' => $currencyId,
            'base_currency_id' => $org->base_currency_id,
            'status' => VendorBillStatus::Paid,
            'paid_amount' => 100,
            'balance_due' => 0,
        ]);

        $this->actingAs($user)
            ->post("/bills/{$bill->id}/void", ['reason' => 'Too late'])
            ->assertRedirect()
            ->assertSessionHas('error', 'Paid bills cannot be voided.');

        $this->assertSame(VendorBillStatus::Paid, $bill->fresh()->status);
    }

    public function test_void_rejects_posted_bill_without_journal_entry(): void
    {
        $user = User::factory()->create();
        [$org, $expense, , $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, 'void-bills');

        $bill = VendorBill::factory()->posted()->create([
            'organization_id' => $org->id,
            'vendor_id' => $vendor->id,
            'created_by' => User::factory()->create()->id,
            'currency_id' => $currencyId,
            'base_currency_id' => $org->base_currency_id,
        ]);

        $this->actingAs($user)
            ->post("/bills/{$bill->id}/void", ['reason' => 'Too late'])
            ->assertRedirect()
            ->assertSessionHas('error', 'A posted journal entry is required to void a posted bill.');
    }

    public function test_void_posted_bill_creates_reversal_entry(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        $voider = User::factory()->create();
        [$org, $expense, , $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, 'create-bills');
        $this->attachRoleWithPermission($poster, $org, 'approve-bills');
        $this->attachRoleWithPermission($voider, $org, 'void-bills');

        $bill = $this->createDraftBill($org, $creator, $expense->id, $vendor->id, $currencyId);

        $this->actingAs($poster)
            ->post("/bills/{$bill->id}/post")
            ->assertRedirect()
            ->assertSessionHas('success', 'Bill posted to ledger.');

        $bill->refresh();
        $originalEntryId = $bill->journal_entry_id;

        $this->actingAs($voider)
            ->post("/bills/{$bill->id}/void", ['reason' => 'Posted in error'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Bill voided.');

        $bill->refresh();
        $this->assertSame(VendorBillStatus::Voided, $bill->status);
        $this->assertSame(0.0, (float) $bill->balance_due);
        $this->assertSame('Posted in error', $bill->void_reason);
        $this->assertNotNull($bill->voided_at);

        $this->assertDatabaseHas('journal_entries', [
            'organization_id' => $org->id,
            'status' => 'draft',
            'reversal_of_id' => $originalEntryId,
            'total_debit' => 100.0,
            'total_credit' => 100.0,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'id' => $originalEntryId,
            'status' => 'reversed',
            'reversal_reason' => 'Posted in error',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'journal-entry.reversed',
            'auditable_type' => JournalEntry::class,
            'auditable_id' => $originalEntryId,
        ]);
    }

    public function test_void_returns_404_for_cross_organization_bill(): void
    {
        $user = User::factory()->create();
        [$orgA, $expenseA, , $vendorA, $currencyIdA] = $this->postingSetup();
        $orgB = Organization::factory()->create();
        $this->attachRoleWithPermission($user, $orgA, 'void-bills');
        $this->attachRoleWithPermission($user, $orgB, 'void-bills');

        $bill = $this->createDraftBill($orgA, $user, $expenseA->id, $vendorA->id, $currencyIdA);

        session(['current_organization_id' => $orgB->id]);

        $this->actingAs($user)
            ->post("/bills/{$bill->id}/void", ['reason' => 'test'])
            ->assertStatus(404);
    }

    public function test_void_returns_error_when_no_organization_selected(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $payable = Account::factory()->create(['organization_id' => $org->id]);
        $vendor = Vendor::factory()->create([
            'organization_id' => $org->id,
            'payable_account_id' => $payable->id,
        ]);
        $expense = Account::factory()->create(['organization_id' => $org->id]);
        $this->attachRoleWithPermission($user, $org, 'void-bills');

        $bill = $this->createDraftBill($org, $user, $expense->id, $vendor->id, $org->base_currency_id);

        $user->organizations()->detach();

        $this->actingAs($user)
            ->post("/bills/{$bill->id}/void", ['reason' => 'test'])
            ->assertRedirect()
            ->assertSessionHas('error', 'No organization selected.');
    }

    // ─── Idempotency Tests ────────────────────────────────────────────────

    public function test_store_replays_idempotent_request(): void
    {
        $user = User::factory()->create();
        [$org, $expense, $taxCode, $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, 'create-bills');

        $this->actingAs($user);
        $this->confirmPassword();

        $payload = $this->storePayload($expense->id, $taxCode->id, $vendor->id, $currencyId);

        $this->post('/bills', $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $firstBill = VendorBill::where('idempotency_key', $payload['idempotency_key'])->firstOrFail();

        $this->post('/bills', $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $secondBill = VendorBill::where('idempotency_key', $payload['idempotency_key'])->first();

        $this->assertSame($firstBill->id, $secondBill->id);
        $this->assertDatabaseCount('vendor_bill_lines', 1);
    }

    // ─── Sequence Tests ───────────────────────────────────────────────────

    public function test_bill_numbers_increment_monotonically(): void
    {
        $user = User::factory()->create();
        [$org, $expense, $taxCode, $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, 'create-bills');

        $this->actingAs($user);
        $this->confirmPassword();

        $payload1 = $this->storePayload($expense->id, $taxCode->id, $vendor->id, $currencyId);
        $payload2 = $this->storePayload($expense->id, $taxCode->id, $vendor->id, $currencyId);

        $this->post('/bills', $payload1)->assertSessionHas('success');
        $this->post('/bills', $payload2)->assertSessionHas('success');

        $first = VendorBill::where('idempotency_key', $payload1['idempotency_key'])->firstOrFail();
        $second = VendorBill::where('idempotency_key', $payload2['idempotency_key'])->firstOrFail();

        $this->assertMatchesRegularExpression('/^BILL-\d{6}$/', $first->bill_number);
        $this->assertMatchesRegularExpression('/^BILL-\d{6}$/', $second->bill_number);

        $firstNum = (int) substr($first->bill_number, -6);
        $secondNum = (int) substr($second->bill_number, -6);
        $this->assertSame($firstNum + 1, $secondNum);
    }
}
