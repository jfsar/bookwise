<?php

namespace Tests\Feature;

use App\Enums\AccountingPeriodStatus;
use App\Enums\CustomerCreditNoteStatus;
use App\Enums\CustomerInvoiceStatus;
use App\Enums\FiscalYearStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Customer;
use App\Models\CustomerCreditNote;
use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceLine;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TaxCode;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerCreditNoteTest extends TestCase
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

    private function creditNoteOrganization(): array
    {
        $org = Organization::factory()->create();
        $revenue = Account::factory()->create(['organization_id' => $org->id]);
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
        $receivable = Account::factory()->create(['organization_id' => $org->id]);
        $customer = Customer::factory()->create([
            'organization_id' => $org->id,
            'receivable_account_id' => $receivable->id,
        ]);

        return [$org, $revenue, $taxCode, $customer, $org->base_currency_id];
    }

    private function postingSetup(): array
    {
        [$org, $revenue, $taxCode, $customer, $currencyId] = $this->creditNoteOrganization();

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

        return [$org, $revenue, $taxCode, $customer, $currencyId, $fiscalYear, $period];
    }

    private function postedInvoice(
        Organization $org,
        User $creator,
        Account $revenue,
        ?TaxCode $taxCode,
        Customer $customer,
        int $currencyId,
        float $subtotal = 100,
        float $tax = 0,
    ): CustomerInvoice {
        $invoice = CustomerInvoice::factory()->posted()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'created_by' => $creator->id,
            'currency_id' => $currencyId,
            'base_currency_id' => $org->base_currency_id,
            'invoice_date' => today()->toDateString(),
            'due_date' => today()->addDays(15)->toDateString(),
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'tax_amount' => $tax,
            'total_amount' => round($subtotal + $tax, 4),
            'paid_amount' => 0,
            'balance_due' => round($subtotal + $tax, 4),
        ]);

        CustomerInvoiceLine::create([
            'organization_id' => $org->id,
            'invoice_id' => $invoice->id,
            'line_number' => 1,
            'description' => 'Test service',
            'quantity' => 1,
            'unit_price' => $subtotal,
            'discount_amount' => 0,
            'tax_amount' => $tax,
            'line_total' => round($subtotal + $tax, 4),
            'revenue_account_id' => $revenue->id,
            'tax_code_id' => $taxCode?->id,
            'currency_id' => $currencyId,
        ]);

        return $invoice;
    }

    private function storePayload(int $customerId, int $invoiceId, float $subtotal = 100, float $tax = 0): array
    {
        return [
            'customer_id' => $customerId,
            'invoice_id' => $invoiceId,
            'date' => today()->toDateString(),
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'reason' => 'Goods returned by customer',
            'idempotency_key' => 'cn-'.fake()->uuid(),
        ];
    }

    // ─── Index Tests ──────────────────────────────────────────────────────

    public function test_index_requires_authentication(): void
    {
        $this->get('/credit-notes')->assertRedirect(route('login'));
    }

    public function test_index_forbids_user_without_permission(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $this->attachRoleWithPermission($user, $org, []);

        $this->actingAs($user)
            ->get('/credit-notes')
            ->assertStatus(403);
    }

    public function test_index_allows_user_with_permission(): void
    {
        $user = User::factory()->create();
        [$org] = $this->creditNoteOrganization();
        $this->attachRoleWithPermission($user, $org, 'view-credit-notes');

        $this->actingAs($user)
            ->get('/credit-notes')
            ->assertOk();
    }

    // ─── Store Tests ──────────────────────────────────────────────────────

    public function test_store_forbids_user_without_permission(): void
    {
        $user = User::factory()->create();
        [$org, $revenue, $taxCode, $customer, $currencyId] = $this->creditNoteOrganization();
        $invoice = $this->postedInvoice($org, $user, $revenue, $taxCode, $customer, $currencyId);
        $this->attachRoleWithPermission($user, $org, []);

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/credit-notes', $this->storePayload($customer->id, $invoice->id))
            ->assertStatus(403);
    }

    public function test_store_creates_draft_credit_note_with_audit(): void
    {
        $user = User::factory()->create();
        [$org, $revenue, $taxCode, $customer, $currencyId] = $this->creditNoteOrganization();
        $invoice = $this->postedInvoice($org, $user, $revenue, $taxCode, $customer, $currencyId, 100, 12);
        $this->attachRoleWithPermission($user, $org, 'create-credit-notes');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/credit-notes', $this->storePayload($customer->id, $invoice->id, 100, 12))
            ->assertRedirect()
            ->assertSessionHas('success');

        $creditNote = CustomerCreditNote::query()
            ->where('organization_id', $org->id)
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        $this->assertSame(CustomerCreditNoteStatus::Draft, $creditNote->status);
        $this->assertStringStartsWith('CN-', $creditNote->credit_note_number);
        $this->assertSame($invoice->id, $creditNote->invoice_id);
        $this->assertSame(100.0, (float) $creditNote->subtotal);
        $this->assertSame(12.0, (float) $creditNote->tax_amount);
        $this->assertSame(112.0, (float) $creditNote->total_amount);
        $this->assertNull($creditNote->journal_entry_id);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'customer-credit-note.created',
            'auditable_type' => CustomerCreditNote::class,
            'auditable_id' => $creditNote->id,
        ]);
    }

    public function test_store_requires_fields(): void
    {
        $user = User::factory()->create();
        [$org] = $this->creditNoteOrganization();
        $this->attachRoleWithPermission($user, $org, 'create-credit-notes');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/credit-notes', [])
            ->assertRedirect()
            ->assertSessionHasErrors(['customer_id', 'invoice_id', 'date', 'subtotal', 'tax_amount', 'reason']);
    }

    public function test_store_rejects_cross_organization_customer(): void
    {
        $user = User::factory()->create();
        [$org, $revenue, $taxCode, , $currencyId] = $this->creditNoteOrganization();
        $otherOrg = Organization::factory()->create();
        $otherReceivable = Account::factory()->create(['organization_id' => $otherOrg->id]);
        $otherCustomer = Customer::factory()->create([
            'organization_id' => $otherOrg->id,
            'receivable_account_id' => $otherReceivable->id,
        ]);
        $this->attachRoleWithPermission($user, $org, 'create-credit-notes');

        $localReceivable = Account::factory()->create(['organization_id' => $org->id]);
        $localCustomer = Customer::factory()->create([
            'organization_id' => $org->id,
            'receivable_account_id' => $localReceivable->id,
        ]);
        $invoice = $this->postedInvoice($org, $user, $revenue, $taxCode, $localCustomer, $currencyId);

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/credit-notes', $this->storePayload($otherCustomer->id, $invoice->id))
            ->assertRedirect()
            ->assertSessionHas('error', 'The customer must belong to the same organization.');
    }

    public function test_store_rejects_inactive_customer(): void
    {
        $user = User::factory()->create();
        [$org, $revenue, $taxCode, $customer, $currencyId] = $this->creditNoteOrganization();
        $customer->update(['status' => 'inactive']);
        $this->attachRoleWithPermission($user, $org, 'create-credit-notes');

        $invoice = $this->postedInvoice($org, $user, $revenue, $taxCode, $customer, $currencyId);

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/credit-notes', $this->storePayload($customer->id, $invoice->id))
            ->assertRedirect()
            ->assertSessionHas('error', 'The customer must be active.');
    }

    public function test_store_rejects_cross_organization_invoice(): void
    {
        $user = User::factory()->create();
        [$org, $revenue, $taxCode, $customer, $currencyId] = $this->creditNoteOrganization();
        $otherOrg = Organization::factory()->create();
        $otherRevenue = Account::factory()->create(['organization_id' => $otherOrg->id]);
        $otherReceivable = Account::factory()->create(['organization_id' => $otherOrg->id]);
        $otherCustomer = Customer::factory()->create([
            'organization_id' => $otherOrg->id,
            'receivable_account_id' => $otherReceivable->id,
        ]);
        $otherInvoice = $this->postedInvoice($otherOrg, $user, $otherRevenue, null, $otherCustomer, $otherOrg->base_currency_id);
        $this->attachRoleWithPermission($user, $org, 'create-credit-notes');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/credit-notes', $this->storePayload($customer->id, $otherInvoice->id))
            ->assertRedirect()
            ->assertSessionHas('error', 'The invoice must belong to the same organization.');
    }

    public function test_store_rejects_invoice_of_another_customer(): void
    {
        $user = User::factory()->create();
        [$org, $revenue, $taxCode, $customer, $currencyId] = $this->creditNoteOrganization();
        $otherCustomer = Customer::factory()->create([
            'organization_id' => $org->id,
            'receivable_account_id' => Account::factory()->create(['organization_id' => $org->id])->id,
        ]);
        $invoice = $this->postedInvoice($org, $user, $revenue, $taxCode, $otherCustomer, $currencyId);
        $this->attachRoleWithPermission($user, $org, 'create-credit-notes');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/credit-notes', $this->storePayload($customer->id, $invoice->id))
            ->assertRedirect()
            ->assertSessionHas('error', 'The invoice must belong to the same customer.');
    }

    public function test_store_rejects_unposted_invoice(): void
    {
        $user = User::factory()->create();
        [$org, $revenue, $taxCode, $customer, $currencyId] = $this->creditNoteOrganization();
        $invoice = CustomerInvoice::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'currency_id' => $currencyId,
            'base_currency_id' => $org->base_currency_id,
            'status' => CustomerInvoiceStatus::Draft,
            'total_amount' => 100,
            'balance_due' => 100,
        ]);
        $this->attachRoleWithPermission($user, $org, 'create-credit-notes');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/credit-notes', $this->storePayload($customer->id, $invoice->id))
            ->assertRedirect()
            ->assertSessionHas('error', 'Only posted invoices can be credited.');
    }

    public function test_store_rejects_credit_exceeding_outstanding_balance(): void
    {
        $user = User::factory()->create();
        [$org, $revenue, $taxCode, $customer, $currencyId] = $this->creditNoteOrganization();
        $invoice = $this->postedInvoice($org, $user, $revenue, $taxCode, $customer, $currencyId, 100, 0);
        $this->attachRoleWithPermission($user, $org, 'create-credit-notes');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/credit-notes', $this->storePayload($customer->id, $invoice->id, 150))
            ->assertRedirect()
            ->assertSessionHas('error', 'The credit note total must not exceed the outstanding balance of the invoice.');
    }

    public function test_store_is_idempotent_via_idempotency_key(): void
    {
        $user = User::factory()->create();
        [$org, $revenue, $taxCode, $customer, $currencyId] = $this->creditNoteOrganization();
        $invoice = $this->postedInvoice($org, $user, $revenue, $taxCode, $customer, $currencyId);
        $this->attachRoleWithPermission($user, $org, 'create-credit-notes');

        $this->actingAs($user);
        $this->confirmPassword();

        $payload = $this->storePayload($customer->id, $invoice->id);
        $payload['idempotency_key'] = 'cn-duplicate-key';

        $this->post('/credit-notes', $payload)->assertSessionHas('success');
        $this->post('/credit-notes', $payload)->assertSessionHas('success');

        $this->assertSame(1, CustomerCreditNote::forOrganization($org)->count());
    }

    // ─── Posting Tests ────────────────────────────────────────────────────

    public function test_post_posts_credit_note_to_ledger(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, $revenue, $taxCode, $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-credit-notes']);
        $this->attachRoleWithPermission($poster, $org, 'approve-credit-notes');

        $invoice = $this->postedInvoice($org, $creator, $revenue, $taxCode, $customer, $currencyId, 100, 12);

        $this->actingAs($creator);
        $this->confirmPassword();
        $this->post('/credit-notes', $this->storePayload($customer->id, $invoice->id, 100, 12))
            ->assertSessionHas('success');

        $creditNote = CustomerCreditNote::forOrganization($org)->firstOrFail();

        $this->actingAs($poster)
            ->post("/credit-notes/{$creditNote->id}/post")
            ->assertRedirect()
            ->assertSessionHas('success', 'Credit note posted to ledger.');

        $creditNote->refresh();
        $this->assertSame(CustomerCreditNoteStatus::Posted, $creditNote->status);
        $this->assertNotNull($creditNote->journal_entry_id);

        $entry = $creditNote->journalEntry->load('lines');
        $this->assertTrue($entry->isBalanced());

        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $customer->receivable_account_id,
            'debit' => 0,
            'credit' => 112,
        ]);
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $revenue->id,
            'debit' => 100,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $taxCode->tax_account_id,
            'debit' => 12,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'customer-credit-note.posted',
            'auditable_type' => CustomerCreditNote::class,
            'auditable_id' => $creditNote->id,
        ]);
    }

    public function test_post_forbids_creator_from_posting_own_credit_note(): void
    {
        $user = User::factory()->create();
        [$org, $revenue, $taxCode, $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, ['create-credit-notes', 'approve-credit-notes']);

        $invoice = $this->postedInvoice($org, $user, $revenue, $taxCode, $customer, $currencyId);

        $creditNote = CustomerCreditNote::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'created_by' => $user->id,
            'currency_id' => $currencyId,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
        ]);

        $this->actingAs($user)
            ->post("/credit-notes/{$creditNote->id}/post")
            ->assertRedirect()
            ->assertSessionHas('error', 'Maker-checker: The final poster cannot be the same person who created the credit note.');

        $this->assertSame(CustomerCreditNoteStatus::Draft, $creditNote->fresh()->status);
    }

    public function test_post_forbids_user_without_permission(): void
    {
        $creator = User::factory()->create();
        $user = User::factory()->create();
        [$org, $revenue, $taxCode, $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, 'create-credit-notes');
        $this->attachRoleWithPermission($user, $org, []);

        $invoice = $this->postedInvoice($org, $creator, $revenue, $taxCode, $customer, $currencyId);

        $creditNote = CustomerCreditNote::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'created_by' => $creator->id,
            'currency_id' => $currencyId,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
        ]);

        $this->actingAs($user)
            ->post("/credit-notes/{$creditNote->id}/post")
            ->assertStatus(403);
    }

    public function test_post_returns_404_for_cross_organization_credit_note(): void
    {
        $creator = User::factory()->create();
        [$orgA, $revenueA, $taxCodeA, $customerA, $currencyIdA] = $this->postingSetup();
        $orgB = Organization::factory()->create();
        $this->attachRoleWithPermission($creator, $orgA, 'create-credit-notes');
        $this->attachRoleWithPermission($creator, $orgB, 'approve-credit-notes');

        $invoice = $this->postedInvoice($orgA, $creator, $revenueA, $taxCodeA, $customerA, $currencyIdA);

        $creditNote = CustomerCreditNote::factory()->create([
            'organization_id' => $orgA->id,
            'customer_id' => $customerA->id,
            'invoice_id' => $invoice->id,
            'created_by' => $creator->id,
            'currency_id' => $currencyIdA,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
        ]);

        session(['current_organization_id' => $orgB->id]);

        $this->actingAs($creator)
            ->post("/credit-notes/{$creditNote->id}/post")
            ->assertStatus(404);
    }

    public function test_post_rejects_closed_period(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, $revenue, $taxCode, $customer, $currencyId, , $period] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-credit-notes']);
        $this->attachRoleWithPermission($poster, $org, 'approve-credit-notes');

        $period->update(['status' => AccountingPeriodStatus::Closed]);

        $invoice = $this->postedInvoice($org, $creator, $revenue, $taxCode, $customer, $currencyId);

        $creditNote = CustomerCreditNote::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'created_by' => $creator->id,
            'currency_id' => $currencyId,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
        ]);

        $this->actingAs($poster)
            ->post("/credit-notes/{$creditNote->id}/post")
            ->assertRedirect()
            ->assertSessionHas('error', 'No open accounting period covers the credit note date.');

        $this->assertSame(CustomerCreditNoteStatus::Draft, $creditNote->fresh()->status);
    }

    public function test_post_rejects_credit_exceeding_outstanding_balance(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, $revenue, $taxCode, $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-credit-notes']);
        $this->attachRoleWithPermission($poster, $org, 'approve-credit-notes');

        $invoice = $this->postedInvoice($org, $creator, $revenue, $taxCode, $customer, $currencyId, 100, 0);

        $creditNote = CustomerCreditNote::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'created_by' => $creator->id,
            'currency_id' => $currencyId,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
        ]);

        $this->actingAs($poster)
            ->post("/credit-notes/{$creditNote->id}/post")
            ->assertSessionHas('success');

        $second = CustomerCreditNote::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'created_by' => $creator->id,
            'currency_id' => $currencyId,
            'subtotal' => 10,
            'tax_amount' => 0,
            'total_amount' => 10,
        ]);

        $this->actingAs($poster)
            ->post("/credit-notes/{$second->id}/post")
            ->assertRedirect()
            ->assertSessionHas('error', 'The credit note total must not exceed the outstanding balance of the invoice.');

        $this->assertSame(CustomerCreditNoteStatus::Draft, $second->fresh()->status);
    }

    public function test_post_returns_generic_error_on_query_exception(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, $revenue, $taxCode, $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-credit-notes']);
        $this->attachRoleWithPermission($poster, $org, 'approve-credit-notes');

        $invoice = $this->postedInvoice($org, $creator, $revenue, $taxCode, $customer, $currencyId);

        $creditNote = CustomerCreditNote::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
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
            ->post("/credit-notes/{$creditNote->id}/post")
            ->assertRedirect()
            ->assertSessionHas('error', 'Unable to post the credit note. Please try again.');
    }

    // ─── Void Tests ───────────────────────────────────────────────────────

    public function test_void_draft_credit_note_with_reason(): void
    {
        $user = User::factory()->create();
        [$org, $revenue, $taxCode, $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, 'void-credit-notes');

        $invoice = $this->postedInvoice($org, $user, $revenue, $taxCode, $customer, $currencyId);

        $creditNote = CustomerCreditNote::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'created_by' => $user->id,
            'currency_id' => $currencyId,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
        ]);

        $this->actingAs($user)
            ->post("/credit-notes/{$creditNote->id}/void", ['reason' => 'Created in error'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Credit note voided.');

        $this->assertSame(CustomerCreditNoteStatus::Voided, $creditNote->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'customer-credit-note.voided',
            'auditable_type' => CustomerCreditNote::class,
            'auditable_id' => $creditNote->id,
        ]);
    }

    public function test_void_requires_reason(): void
    {
        $user = User::factory()->create();
        [$org, $revenue, $taxCode, $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, 'void-credit-notes');

        $invoice = $this->postedInvoice($org, $user, $revenue, $taxCode, $customer, $currencyId);

        $creditNote = CustomerCreditNote::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'created_by' => $user->id,
            'currency_id' => $currencyId,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
        ]);

        $this->actingAs($user)
            ->post("/credit-notes/{$creditNote->id}/void", [])
            ->assertRedirect()
            ->assertSessionHasErrors(['reason']);
    }

    public function test_void_forbids_user_without_permission(): void
    {
        $user = User::factory()->create();
        [$org, $revenue, $taxCode, $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, []);

        $invoice = $this->postedInvoice($org, $user, $revenue, $taxCode, $customer, $currencyId);

        $creditNote = CustomerCreditNote::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'created_by' => $user->id,
            'currency_id' => $currencyId,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
        ]);

        $this->actingAs($user)
            ->post("/credit-notes/{$creditNote->id}/void", ['reason' => 'test'])
            ->assertStatus(403);
    }

    public function test_void_rejects_non_draft_credit_note(): void
    {
        $user = User::factory()->create();
        [$org, $revenue, $taxCode, $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, 'void-credit-notes');

        $invoice = $this->postedInvoice($org, $user, $revenue, $taxCode, $customer, $currencyId);

        $creditNote = CustomerCreditNote::factory()->posted()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'created_by' => $user->id,
            'currency_id' => $currencyId,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
        ]);

        $this->actingAs($user)
            ->post("/credit-notes/{$creditNote->id}/void", ['reason' => 'test'])
            ->assertRedirect()
            ->assertSessionHas('error', 'Only draft credit notes can be voided.');

        $this->assertSame(CustomerCreditNoteStatus::Posted, $creditNote->fresh()->status);
    }
}
