<?php

namespace Tests\Feature;

use App\Enums\AccountingPeriodStatus;
use App\Enums\FiscalYearStatus;
use App\Enums\VendorPaymentStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VendorPaymentTest extends TestCase
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

    private function paymentOrganization(): array
    {
        $org = Organization::factory()->create();
        $payable = Account::factory()->create(['organization_id' => $org->id]);
        $cash = Account::factory()->create(['organization_id' => $org->id]);
        $vendor = Vendor::factory()->create([
            'organization_id' => $org->id,
            'payable_account_id' => $payable->id,
        ]);

        return [$org, $payable, $cash, $vendor, $org->base_currency_id];
    }

    private function storePayload(int $vendorId, int $cashAccountId, int $currencyId): array
    {
        return [
            'vendor_id' => $vendorId,
            'payment_date' => today()->toDateString(),
            'currency_id' => $currencyId,
            'exchange_rate' => 1,
            'amount' => 100,
            'payment_method' => 'bank_transfer',
            'cash_account_id' => $cashAccountId,
            'reference' => 'REF-001',
            'notes' => 'Test payment',
            'idempotency_key' => 'vpay-'.fake()->uuid(),
        ];
    }

    private function postingSetup(): array
    {
        [$org, $payable, $cash, $vendor, $currencyId] = $this->paymentOrganization();

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

        return [$org, $payable, $cash, $vendor, $currencyId, $fiscalYear, $period];
    }

    private function createDraftPayment(
        Organization $org,
        User $creator,
        int $vendorId,
        int $currencyId,
        ?int $cashAccountId = null,
        float $amount = 100,
    ): VendorPayment {
        return VendorPayment::factory()->create([
            'organization_id' => $org->id,
            'vendor_id' => $vendorId,
            'created_by' => $creator->id,
            'currency_id' => $currencyId,
            'base_currency_id' => $org->base_currency_id,
            'status' => VendorPaymentStatus::Draft,
            'payment_date' => today()->toDateString(),
            'amount' => $amount,
            'cash_account_id' => $cashAccountId,
        ]);
    }

    // ─── Index Tests ──────────────────────────────────────────────────────

    public function test_index_requires_authentication(): void
    {
        $this->get('/vendor-payments')->assertRedirect(route('login'));
    }

    public function test_index_forbids_user_without_permission(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $this->attachRoleWithPermission($user, $org, []);

        $this->actingAs($user)
            ->get('/vendor-payments')
            ->assertStatus(403);
    }

    public function test_index_allows_user_with_permission(): void
    {
        $user = User::factory()->create();
        [$org] = $this->paymentOrganization();
        $this->attachRoleWithPermission($user, $org, 'view-vendor-payments');

        $this->actingAs($user)
            ->get('/vendor-payments')
            ->assertOk();
    }

    // ─── Store Tests ──────────────────────────────────────────────────────

    public function test_store_forbids_user_without_permission(): void
    {
        $user = User::factory()->create();
        [$org, , $cash, $vendor, $currencyId] = $this->paymentOrganization();
        $this->attachRoleWithPermission($user, $org, []);

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/vendor-payments', $this->storePayload($vendor->id, $cash->id, $currencyId))
            ->assertStatus(403);
    }

    public function test_store_creates_draft_payment_with_audit(): void
    {
        $user = User::factory()->create();
        [$org, , $cash, $vendor, $currencyId] = $this->paymentOrganization();
        $this->attachRoleWithPermission($user, $org, 'create-vendor-payments');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/vendor-payments', $this->storePayload($vendor->id, $cash->id, $currencyId))
            ->assertRedirect()
            ->assertSessionHas('success');

        $payment = VendorPayment::query()
            ->where('organization_id', $org->id)
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        $this->assertSame(VendorPaymentStatus::Draft, $payment->status);
        $this->assertStringStartsWith('VPAY-', $payment->payment_number);
        $this->assertSame(100.0, (float) $payment->amount);
        $this->assertSame($cash->id, $payment->cash_account_id);
        $this->assertNull($payment->journal_entry_id);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'vendor-payment.created',
            'auditable_type' => VendorPayment::class,
            'auditable_id' => $payment->id,
        ]);
    }

    public function test_store_requires_fields(): void
    {
        $user = User::factory()->create();
        [$org, , $cash, , $currencyId] = $this->paymentOrganization();
        $this->attachRoleWithPermission($user, $org, 'create-vendor-payments');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/vendor-payments', [])
            ->assertRedirect()
            ->assertSessionHasErrors(['vendor_id', 'payment_date', 'currency_id', 'amount', 'payment_method', 'cash_account_id']);
    }

    public function test_store_rejects_cross_organization_vendor(): void
    {
        $user = User::factory()->create();
        [$org, , $cash, , $currencyId] = $this->paymentOrganization();
        $otherOrg = Organization::factory()->create();
        $otherPayable = Account::factory()->create(['organization_id' => $otherOrg->id]);
        $otherVendor = Vendor::factory()->create([
            'organization_id' => $otherOrg->id,
            'payable_account_id' => $otherPayable->id,
        ]);
        $this->attachRoleWithPermission($user, $org, 'create-vendor-payments');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/vendor-payments', $this->storePayload($otherVendor->id, $cash->id, $currencyId))
            ->assertRedirect()
            ->assertSessionHas('error', 'The vendor must belong to the same organization.');
    }

    public function test_store_rejects_inactive_vendor(): void
    {
        $user = User::factory()->create();
        [$org, , $cash, $vendor, $currencyId] = $this->paymentOrganization();
        $vendor->update(['status' => 'inactive']);
        $this->attachRoleWithPermission($user, $org, 'create-vendor-payments');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/vendor-payments', $this->storePayload($vendor->id, $cash->id, $currencyId))
            ->assertRedirect()
            ->assertSessionHas('error', 'The vendor must be active.');
    }

    public function test_store_rejects_cross_organization_cash_account(): void
    {
        $user = User::factory()->create();
        [$org, , , $vendor, $currencyId] = $this->paymentOrganization();
        $otherOrg = Organization::factory()->create();
        $otherCash = Account::factory()->create(['organization_id' => $otherOrg->id]);
        $this->attachRoleWithPermission($user, $org, 'create-vendor-payments');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/vendor-payments', $this->storePayload($vendor->id, $otherCash->id, $currencyId))
            ->assertRedirect()
            ->assertSessionHas('error', 'The cash account must belong to the same organization.');
    }

    public function test_store_is_idempotent_via_idempotency_key(): void
    {
        $user = User::factory()->create();
        [$org, , $cash, $vendor, $currencyId] = $this->paymentOrganization();
        $this->attachRoleWithPermission($user, $org, 'create-vendor-payments');

        $this->actingAs($user);
        $this->confirmPassword();

        $payload = $this->storePayload($vendor->id, $cash->id, $currencyId);
        $payload['idempotency_key'] = 'vpay-duplicate-key';

        $this->post('/vendor-payments', $payload)->assertSessionHas('success');
        $this->post('/vendor-payments', $payload)->assertSessionHas('success');

        $this->assertSame(1, VendorPayment::forOrganization($org)->count());
    }

    // ─── Posting Tests ────────────────────────────────────────────────────

    public function test_post_posts_payment_to_ledger(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, $payable, $cash, $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-vendor-payments']);
        $this->attachRoleWithPermission($poster, $org, 'approve-vendor-payments');

        $payment = $this->createDraftPayment($org, $creator, $vendor->id, $currencyId, $cash->id);

        $this->actingAs($poster)
            ->post("/vendor-payments/{$payment->id}/post")
            ->assertRedirect()
            ->assertSessionHas('success', 'Payment posted to ledger.');

        $payment->refresh();
        $this->assertSame(VendorPaymentStatus::Posted, $payment->status);
        $this->assertNotNull($payment->journal_entry_id);

        $this->assertDatabaseHas('journal_entries', [
            'organization_id' => $org->id,
            'status' => 'posted',
        ]);

        $entry = $payment->journalEntry->load('lines');
        $this->assertTrue($entry->isBalanced());

        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $payable->id,
            'debit' => 100.0,
            'credit' => 0.0,
        ]);
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $cash->id,
            'debit' => 0.0,
            'credit' => 100.0,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'vendor-payment.posted',
            'auditable_type' => VendorPayment::class,
            'auditable_id' => $payment->id,
        ]);
    }

    public function test_post_creates_ap_debit_and_cash_credit_lines(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, $payable, $cash, $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-vendor-payments']);
        $this->attachRoleWithPermission($poster, $org, 'approve-vendor-payments');

        $payment = $this->createDraftPayment($org, $creator, $vendor->id, $currencyId, $cash->id);

        $this->actingAs($poster)
            ->post("/vendor-payments/{$payment->id}/post")
            ->assertSessionHas('success', 'Payment posted to ledger.');

        $entry = $payment->fresh()->journalEntry->load('lines');

        // Per SRS 7.31: Accounts Payable DR, Cash/Bank CR.
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $vendor->payable_account_id,
            'debit' => 100.0,
            'credit' => 0.0,
            'description' => 'Vendor payment applied to accounts payable',
        ]);

        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $cash->id,
            'debit' => 0.0,
            'credit' => 100.0,
            'description' => 'Cash paid to vendor',
        ]);
    }

    public function test_post_forbids_creator_from_posting_own_payment(): void
    {
        $user = User::factory()->create();
        [$org, , $cash, $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, ['create-vendor-payments', 'approve-vendor-payments']);

        $payment = $this->createDraftPayment($org, $user, $vendor->id, $currencyId, $cash->id);

        $this->actingAs($user)
            ->post("/vendor-payments/{$payment->id}/post")
            ->assertRedirect()
            ->assertSessionHas('error', 'Maker-checker: The final poster cannot be the same person who created the payment.');

        $this->assertSame(VendorPaymentStatus::Draft, $payment->fresh()->status);
    }

    public function test_post_forbids_user_without_permission(): void
    {
        $creator = User::factory()->create();
        $user = User::factory()->create();
        [$org, , $cash, $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, 'create-vendor-payments');
        $this->attachRoleWithPermission($user, $org, []);

        $payment = $this->createDraftPayment($org, $creator, $vendor->id, $currencyId, $cash->id);

        $this->actingAs($user)
            ->post("/vendor-payments/{$payment->id}/post")
            ->assertStatus(403);
    }

    public function test_post_returns_404_for_cross_organization_payment(): void
    {
        $creator = User::factory()->create();
        [$orgA, , $cashA, $vendorA, $currencyIdA] = $this->postingSetup();
        $orgB = Organization::factory()->create();
        $this->attachRoleWithPermission($creator, $orgA, 'create-vendor-payments');
        $this->attachRoleWithPermission($creator, $orgB, 'approve-vendor-payments');

        $payment = $this->createDraftPayment($orgA, $creator, $vendorA->id, $currencyIdA, $cashA->id);

        session(['current_organization_id' => $orgB->id]);

        $this->actingAs($creator)
            ->post("/vendor-payments/{$payment->id}/post")
            ->assertStatus(404);
    }

    public function test_post_rejects_closed_period(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, , $cash, $vendor, $currencyId, , $period] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-vendor-payments']);
        $this->attachRoleWithPermission($poster, $org, 'approve-vendor-payments');

        $period->update(['status' => AccountingPeriodStatus::Closed]);

        $payment = $this->createDraftPayment($org, $creator, $vendor->id, $currencyId, $cash->id);

        $this->actingAs($poster)
            ->post("/vendor-payments/{$payment->id}/post")
            ->assertRedirect()
            ->assertSessionHas('error', 'No open accounting period covers the payment date.');

        $this->assertSame(VendorPaymentStatus::Draft, $payment->fresh()->status);
    }

    public function test_post_requires_debit_source_account(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, , , $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-vendor-payments']);
        $this->attachRoleWithPermission($poster, $org, 'approve-vendor-payments');

        $payment = $this->createDraftPayment($org, $creator, $vendor->id, $currencyId);

        $this->actingAs($poster)
            ->post("/vendor-payments/{$payment->id}/post")
            ->assertRedirect()
            ->assertSessionHas('error', 'A cash or bank account is required before the payment can be posted.');

        $this->assertSame(VendorPaymentStatus::Draft, $payment->fresh()->status);
    }

    public function test_post_returns_generic_error_on_query_exception(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, , $cash, $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-vendor-payments']);
        $this->attachRoleWithPermission($poster, $org, 'approve-vendor-payments');

        $payment = $this->createDraftPayment($org, $creator, $vendor->id, $currencyId, $cash->id);

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn(['description']);
        });

        $this->actingAs($poster)
            ->post("/vendor-payments/{$payment->id}/post")
            ->assertRedirect()
            ->assertSessionHas('error', 'Unable to post the payment. Please try again.');
    }

    // ─── Void Tests ───────────────────────────────────────────────────────

    public function test_void_draft_payment_with_reason(): void
    {
        $creator = User::factory()->create();
        $voider = User::factory()->create();
        [$org, , $cash, $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-vendor-payments']);
        $this->attachRoleWithPermission($voider, $org, 'void-vendor-payments');

        $payment = $this->createDraftPayment($org, $creator, $vendor->id, $currencyId, $cash->id);

        $this->actingAs($voider)
            ->post("/vendor-payments/{$payment->id}/void", ['reason' => 'Created in error'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Payment voided.');

        $payment->refresh();
        $this->assertSame(VendorPaymentStatus::Voided, $payment->status);
        $this->assertNull($payment->journal_entry_id);
        $this->assertSame('Created in error', $payment->void_reason);
        $this->assertNotNull($payment->voided_at);
        $this->assertSame($voider->id, $payment->voided_by);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'vendor-payment.voided',
            'auditable_type' => VendorPayment::class,
            'auditable_id' => $payment->id,
        ]);
    }

    public function test_void_requires_reason(): void
    {
        $creator = User::factory()->create();
        $voider = User::factory()->create();
        [$org, , $cash, $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-vendor-payments']);
        $this->attachRoleWithPermission($voider, $org, 'void-vendor-payments');

        $payment = $this->createDraftPayment($org, $creator, $vendor->id, $currencyId, $cash->id);

        $this->actingAs($voider)
            ->post("/vendor-payments/{$payment->id}/void", [])
            ->assertRedirect()
            ->assertSessionHasErrors(['reason']);
    }

    public function test_void_forbids_user_without_permission(): void
    {
        $creator = User::factory()->create();
        $user = User::factory()->create();
        [$org, , $cash, $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-vendor-payments']);
        $this->attachRoleWithPermission($user, $org, []);

        $payment = $this->createDraftPayment($org, $creator, $vendor->id, $currencyId, $cash->id);

        $this->actingAs($user)
            ->post("/vendor-payments/{$payment->id}/void", ['reason' => 'test'])
            ->assertStatus(403);
    }

    public function test_void_rejects_already_voided_payment(): void
    {
        $creator = User::factory()->create();
        $voider = User::factory()->create();
        [$org, , $cash, $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-vendor-payments']);
        $this->attachRoleWithPermission($voider, $org, 'void-vendor-payments');

        $payment = $this->createDraftPayment($org, $creator, $vendor->id, $currencyId, $cash->id);
        $payment->update(['status' => VendorPaymentStatus::Voided]);

        $this->actingAs($voider)
            ->post("/vendor-payments/{$payment->id}/void", ['reason' => 'test'])
            ->assertRedirect()
            ->assertSessionHas('error', 'Only draft or posted payments can be voided.');
    }

    public function test_void_posted_payment_reverses_entry(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        $voider = User::factory()->create();
        [$org, , $cash, $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-vendor-payments']);
        $this->attachRoleWithPermission($poster, $org, 'approve-vendor-payments');
        $this->attachRoleWithPermission($voider, $org, 'void-vendor-payments');

        $payment = $this->createDraftPayment($org, $creator, $vendor->id, $currencyId, $cash->id);

        $this->actingAs($poster)
            ->post("/vendor-payments/{$payment->id}/post")
            ->assertRedirect()
            ->assertSessionHas('success', 'Payment posted to ledger.');

        $payment->refresh();
        $originalEntryId = $payment->journal_entry_id;

        $this->actingAs($voider)
            ->post("/vendor-payments/{$payment->id}/void", ['reason' => 'Duplicate payment'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Payment voided.');

        $payment->refresh();
        $this->assertSame(VendorPaymentStatus::Voided, $payment->status);
        $this->assertSame('Duplicate payment', $payment->void_reason);
        $this->assertNotNull($payment->voided_at);

        $this->assertDatabaseHas('journal_entries', [
            'organization_id' => $org->id,
            'status' => 'draft',
            'reversal_of_id' => $originalEntryId,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'id' => $originalEntryId,
            'status' => 'reversed',
            'reversal_reason' => 'Duplicate payment',
        ]);
    }

    // ─── Sequence Tests ───────────────────────────────────────────────────

    public function test_payment_numbers_increment_monotonically(): void
    {
        $user = User::factory()->create();
        [$org, , $cash, $vendor, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, 'create-vendor-payments');

        $this->actingAs($user);
        $this->confirmPassword();

        $payload1 = $this->storePayload($vendor->id, $cash->id, $currencyId);
        $payload2 = $this->storePayload($vendor->id, $cash->id, $currencyId);

        $this->post('/vendor-payments', $payload1)->assertSessionHas('success');
        $this->post('/vendor-payments', $payload2)->assertSessionHas('success');

        $first = VendorPayment::where('idempotency_key', $payload1['idempotency_key'])->firstOrFail();
        $second = VendorPayment::where('idempotency_key', $payload2['idempotency_key'])->firstOrFail();

        $this->assertMatchesRegularExpression('/^VPAY-\d{6}$/', $first->payment_number);
        $this->assertMatchesRegularExpression('/^VPAY-\d{6}$/', $second->payment_number);

        $firstNum = (int) substr($first->payment_number, -6);
        $secondNum = (int) substr($second->payment_number, -6);
        $this->assertSame($firstNum + 1, $secondNum);
    }
}
