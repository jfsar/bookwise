<?php

namespace Tests\Feature;

use App\Enums\AccountingPeriodStatus;
use App\Enums\CustomerInvoiceStatus;
use App\Enums\CustomerPaymentStatus;
use App\Enums\FiscalYearStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerPaymentTest extends TestCase
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
        $receivable = Account::factory()->create(['organization_id' => $org->id]);
        $cash = Account::factory()->create(['organization_id' => $org->id]);
        $customer = Customer::factory()->create([
            'organization_id' => $org->id,
            'receivable_account_id' => $receivable->id,
        ]);

        return [$org, $receivable, $cash, $customer, $org->base_currency_id];
    }

    private function storePayload(int $customerId, int $cashAccountId, int $currencyId): array
    {
        return [
            'customer_id' => $customerId,
            'payment_date' => today()->toDateString(),
            'currency_id' => $currencyId,
            'exchange_rate' => 1,
            'amount' => 100,
            'payment_method' => 'bank_transfer',
            'cash_account_id' => $cashAccountId,
            'reference' => 'REF-001',
            'notes' => 'Test payment',
            'idempotency_key' => 'pay-'.fake()->uuid(),
        ];
    }

    private function postingSetup(): array
    {
        [$org, $receivable, $cash, $customer, $currencyId] = $this->paymentOrganization();

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

        return [$org, $receivable, $cash, $customer, $currencyId, $fiscalYear, $period];
    }

    private function createDraftPayment(
        Organization $org,
        User $creator,
        int $customerId,
        int $currencyId,
        ?int $cashAccountId = null,
        float $amount = 100,
    ): CustomerPayment {
        return CustomerPayment::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customerId,
            'created_by' => $creator->id,
            'currency_id' => $currencyId,
            'base_currency_id' => $org->base_currency_id,
            'status' => CustomerPaymentStatus::Draft,
            'payment_date' => today()->toDateString(),
            'amount' => $amount,
            'cash_account_id' => $cashAccountId,
        ]);
    }

    // ─── Index Tests ──────────────────────────────────────────────────────

    public function test_index_requires_authentication(): void
    {
        $this->get('/payments')->assertRedirect(route('login'));
    }

    public function test_index_forbids_user_without_permission(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $this->attachRoleWithPermission($user, $org, []);

        $this->actingAs($user)
            ->get('/payments')
            ->assertStatus(403);
    }

    public function test_index_allows_user_with_permission(): void
    {
        $user = User::factory()->create();
        [$org] = $this->paymentOrganization();
        $this->attachRoleWithPermission($user, $org, 'view-payments');

        $this->actingAs($user)
            ->get('/payments')
            ->assertOk();
    }

    // ─── Store Tests ──────────────────────────────────────────────────────

    public function test_store_forbids_user_without_permission(): void
    {
        $user = User::factory()->create();
        [$org, , $cash, $customer, $currencyId] = $this->paymentOrganization();
        $this->attachRoleWithPermission($user, $org, []);

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/payments', $this->storePayload($customer->id, $cash->id, $currencyId))
            ->assertStatus(403);
    }

    public function test_store_creates_draft_payment_with_audit(): void
    {
        $user = User::factory()->create();
        [$org, , $cash, $customer, $currencyId] = $this->paymentOrganization();
        $this->attachRoleWithPermission($user, $org, 'create-payments');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/payments', $this->storePayload($customer->id, $cash->id, $currencyId))
            ->assertRedirect()
            ->assertSessionHas('success');

        $payment = CustomerPayment::query()
            ->where('organization_id', $org->id)
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        $this->assertSame(CustomerPaymentStatus::Draft, $payment->status);
        $this->assertStringStartsWith('PAY-', $payment->payment_number);
        $this->assertSame(100.0, (float) $payment->amount);
        $this->assertSame($cash->id, $payment->cash_account_id);
        $this->assertNull($payment->journal_entry_id);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'customer-payment.created',
            'auditable_type' => CustomerPayment::class,
            'auditable_id' => $payment->id,
        ]);
    }

    public function test_store_requires_fields(): void
    {
        $user = User::factory()->create();
        [$org, , $cash, , $currencyId] = $this->paymentOrganization();
        $this->attachRoleWithPermission($user, $org, 'create-payments');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/payments', [])
            ->assertRedirect()
            ->assertSessionHasErrors(['customer_id', 'payment_date', 'currency_id', 'amount', 'payment_method', 'cash_account_id']);
    }

    public function test_store_rejects_cross_organization_customer(): void
    {
        $user = User::factory()->create();
        [$org, , $cash, , $currencyId] = $this->paymentOrganization();
        $otherOrg = Organization::factory()->create();
        $otherReceivable = Account::factory()->create(['organization_id' => $otherOrg->id]);
        $otherCustomer = Customer::factory()->create([
            'organization_id' => $otherOrg->id,
            'receivable_account_id' => $otherReceivable->id,
        ]);
        $this->attachRoleWithPermission($user, $org, 'create-payments');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/payments', $this->storePayload($otherCustomer->id, $cash->id, $currencyId))
            ->assertRedirect()
            ->assertSessionHas('error', 'The customer must belong to the same organization.');
    }

    public function test_store_rejects_inactive_customer(): void
    {
        $user = User::factory()->create();
        [$org, , $cash, $customer, $currencyId] = $this->paymentOrganization();
        $customer->update(['status' => 'inactive']);
        $this->attachRoleWithPermission($user, $org, 'create-payments');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/payments', $this->storePayload($customer->id, $cash->id, $currencyId))
            ->assertRedirect()
            ->assertSessionHas('error', 'The customer must be active.');
    }

    public function test_store_rejects_cross_organization_cash_account(): void
    {
        $user = User::factory()->create();
        [$org, , , $customer, $currencyId] = $this->paymentOrganization();
        $otherOrg = Organization::factory()->create();
        $otherCash = Account::factory()->create(['organization_id' => $otherOrg->id]);
        $this->attachRoleWithPermission($user, $org, 'create-payments');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/payments', $this->storePayload($customer->id, $otherCash->id, $currencyId))
            ->assertRedirect()
            ->assertSessionHas('error', 'The cash account must belong to the same organization.');
    }

    public function test_store_is_idempotent_via_idempotency_key(): void
    {
        $user = User::factory()->create();
        [$org, , $cash, $customer, $currencyId] = $this->paymentOrganization();
        $this->attachRoleWithPermission($user, $org, 'create-payments');

        $this->actingAs($user);
        $this->confirmPassword();

        $payload = $this->storePayload($customer->id, $cash->id, $currencyId);
        $payload['idempotency_key'] = 'pay-duplicate-key';

        $this->post('/payments', $payload)->assertSessionHas('success');
        $this->post('/payments', $payload)->assertSessionHas('success');

        $this->assertSame(1, CustomerPayment::forOrganization($org)->count());
    }

    // ─── Posting Tests ────────────────────────────────────────────────────

    public function test_post_posts_payment_to_ledger(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, $receivable, $cash, $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-payments']);
        $this->attachRoleWithPermission($poster, $org, 'approve-payments');

        $payment = $this->createDraftPayment($org, $creator, $customer->id, $currencyId, $cash->id);

        $this->actingAs($poster)
            ->post("/payments/{$payment->id}/post")
            ->assertRedirect()
            ->assertSessionHas('success', 'Payment posted to ledger.');

        $payment->refresh();
        $this->assertSame(CustomerPaymentStatus::Posted, $payment->status);
        $this->assertNotNull($payment->journal_entry_id);

        $this->assertDatabaseHas('journal_entries', [
            'organization_id' => $org->id,
            'status' => 'posted',
        ]);

        $entry = $payment->journalEntry->load('lines');
        $this->assertTrue($entry->isBalanced());

        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $cash->id,
            'debit' => 100,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $receivable->id,
            'debit' => 0,
            'credit' => 100,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'customer-payment.posted',
            'auditable_type' => CustomerPayment::class,
            'auditable_id' => $payment->id,
        ]);
    }

    public function test_post_forbids_creator_from_posting_own_payment(): void
    {
        $user = User::factory()->create();
        [$org, , $cash, $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, ['create-payments', 'approve-payments']);

        $payment = $this->createDraftPayment($org, $user, $customer->id, $currencyId, $cash->id);

        $this->actingAs($user)
            ->post("/payments/{$payment->id}/post")
            ->assertRedirect()
            ->assertSessionHas('error', 'Maker-checker: The final poster cannot be the same person who created the payment.');

        $this->assertSame(CustomerPaymentStatus::Draft, $payment->fresh()->status);
    }

    public function test_post_forbids_user_without_permission(): void
    {
        $creator = User::factory()->create();
        $user = User::factory()->create();
        [$org, , $cash, $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, 'create-payments');
        $this->attachRoleWithPermission($user, $org, []);

        $payment = $this->createDraftPayment($org, $creator, $customer->id, $currencyId, $cash->id);

        $this->actingAs($user)
            ->post("/payments/{$payment->id}/post")
            ->assertStatus(403);
    }

    public function test_post_returns_404_for_cross_organization_payment(): void
    {
        $creator = User::factory()->create();
        [$orgA, , $cashA, $customerA, $currencyIdA] = $this->postingSetup();
        $orgB = Organization::factory()->create();
        $this->attachRoleWithPermission($creator, $orgA, 'create-payments');
        $this->attachRoleWithPermission($creator, $orgB, 'approve-payments');

        $payment = $this->createDraftPayment($orgA, $creator, $customerA->id, $currencyIdA, $cashA->id);

        session(['current_organization_id' => $orgB->id]);

        $this->actingAs($creator)
            ->post("/payments/{$payment->id}/post")
            ->assertStatus(404);
    }

    public function test_post_rejects_closed_period(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, , $cash, $customer, $currencyId, , $period] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-payments']);
        $this->attachRoleWithPermission($poster, $org, 'approve-payments');

        $period->update(['status' => AccountingPeriodStatus::Closed]);

        $payment = $this->createDraftPayment($org, $creator, $customer->id, $currencyId, $cash->id);

        $this->actingAs($poster)
            ->post("/payments/{$payment->id}/post")
            ->assertRedirect()
            ->assertSessionHas('error', 'No open accounting period covers the payment date.');

        $this->assertSame(CustomerPaymentStatus::Draft, $payment->fresh()->status);
    }

    public function test_post_requires_debit_source_account(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, , , $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-payments']);
        $this->attachRoleWithPermission($poster, $org, 'approve-payments');

        $payment = $this->createDraftPayment($org, $creator, $customer->id, $currencyId);

        $this->actingAs($poster)
            ->post("/payments/{$payment->id}/post")
            ->assertRedirect()
            ->assertSessionHas('error', 'A cash or bank account is required before the payment can be posted.');

        $this->assertSame(CustomerPaymentStatus::Draft, $payment->fresh()->status);
    }

    public function test_post_returns_generic_error_on_query_exception(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, , $cash, $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-payments']);
        $this->attachRoleWithPermission($poster, $org, 'approve-payments');

        $payment = $this->createDraftPayment($org, $creator, $customer->id, $currencyId, $cash->id);

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn(['description']);
        });

        $this->actingAs($poster)
            ->post("/payments/{$payment->id}/post")
            ->assertRedirect()
            ->assertSessionHas('error', 'Unable to post the payment. Please try again.');
    }

    // ─── Void Tests ───────────────────────────────────────────────────────

    public function test_void_draft_payment_with_reason(): void
    {
        $creator = User::factory()->create();
        $voider = User::factory()->create();
        [$org, , $cash, $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-payments']);
        $this->attachRoleWithPermission($voider, $org, 'void-payments');

        $payment = $this->createDraftPayment($org, $creator, $customer->id, $currencyId, $cash->id);

        $this->actingAs($voider)
            ->post("/payments/{$payment->id}/void", ['reason' => 'Created in error'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Payment voided.');

        $payment->refresh();
        $this->assertSame(CustomerPaymentStatus::Voided, $payment->status);
        $this->assertNull($payment->journal_entry_id);
        $this->assertSame('Created in error', $payment->void_reason);
        $this->assertNotNull($payment->voided_at);
        $this->assertSame($voider->id, $payment->voided_by);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'customer-payment.voided',
            'auditable_type' => CustomerPayment::class,
            'auditable_id' => $payment->id,
        ]);
    }

    public function test_void_requires_reason(): void
    {
        $creator = User::factory()->create();
        $voider = User::factory()->create();
        [$org, , $cash, $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-payments']);
        $this->attachRoleWithPermission($voider, $org, 'void-payments');

        $payment = $this->createDraftPayment($org, $creator, $customer->id, $currencyId, $cash->id);

        $this->actingAs($voider)
            ->post("/payments/{$payment->id}/void", [])
            ->assertRedirect()
            ->assertSessionHasErrors(['reason']);
    }

    public function test_void_forbids_user_without_permission(): void
    {
        $creator = User::factory()->create();
        $user = User::factory()->create();
        [$org, , $cash, $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-payments']);
        $this->attachRoleWithPermission($user, $org, []);

        $payment = $this->createDraftPayment($org, $creator, $customer->id, $currencyId, $cash->id);

        $this->actingAs($user)
            ->post("/payments/{$payment->id}/void", ['reason' => 'test'])
            ->assertStatus(403);
    }

    public function test_void_rejects_already_voided_payment(): void
    {
        $creator = User::factory()->create();
        $voider = User::factory()->create();
        [$org, , $cash, $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-payments']);
        $this->attachRoleWithPermission($voider, $org, 'void-payments');

        $payment = $this->createDraftPayment($org, $creator, $customer->id, $currencyId, $cash->id);
        $payment->update(['status' => CustomerPaymentStatus::Voided]);

        $this->actingAs($voider)
            ->post("/payments/{$payment->id}/void", ['reason' => 'test'])
            ->assertRedirect()
            ->assertSessionHas('error', 'Only draft or posted payments can be voided.');
    }

    public function test_void_posted_payment_reverses_entry_and_restores_invoice(): void
    {
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $poster = User::factory()->create();
        $voider = User::factory()->create();
        [$org, $receivable, $cash, $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-payments']);
        $this->attachRoleWithPermission($allocator, $org, 'allocate-payments');
        $this->attachRoleWithPermission($poster, $org, 'approve-payments');
        $this->attachRoleWithPermission($voider, $org, 'void-payments');

        $invoice = CustomerInvoice::factory()->posted()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'created_by' => $creator->id,
            'currency_id' => $currencyId,
            'base_currency_id' => $org->base_currency_id,
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 100,
            'paid_amount' => 0,
            'balance_due' => 100,
        ]);

        $payment = $this->createDraftPayment($org, $creator, $customer->id, $currencyId, $cash->id);

        $this->actingAs($allocator)
            ->post("/payments/{$payment->id}/allocate", ['allocations' => [['invoice_id' => $invoice->id, 'amount' => 100]]])
            ->assertRedirect()
            ->assertSessionHas('success', 'Payment allocation saved.');

        $this->actingAs($poster)
            ->post("/payments/{$payment->id}/post")
            ->assertRedirect()
            ->assertSessionHas('success', 'Payment posted to ledger.');

        $payment->refresh();
        $this->assertSame(CustomerInvoiceStatus::Paid, $invoice->fresh()->status);
        $originalEntryId = $payment->journal_entry_id;

        $this->actingAs($voider)
            ->post("/payments/{$payment->id}/void", ['reason' => 'Duplicate payment'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Payment voided.');

        $payment->refresh();
        $this->assertSame(CustomerPaymentStatus::Voided, $payment->status);
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

        $invoice->refresh();
        $this->assertSame(CustomerInvoiceStatus::Posted, $invoice->status);
        $this->assertSame(0.0, (float) $invoice->paid_amount);
        $this->assertSame(100.0, (float) $invoice->balance_due);
    }
}
