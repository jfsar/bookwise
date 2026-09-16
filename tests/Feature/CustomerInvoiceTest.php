<?php

namespace Tests\Feature;

use App\Enums\AccountingPeriodStatus;
use App\Enums\CustomerInvoiceStatus;
use App\Enums\FiscalYearStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Customer;
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

class CustomerInvoiceTest extends TestCase
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

    private function invoiceOrganization(): array
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

    private function storePayload(int $revenueAccountId, int $taxCodeId, int $customerId, int $currencyId): array
    {
        return [
            'customer_id' => $customerId,
            'invoice_date' => today()->toDateString(),
            'due_date' => today()->addDays(15)->toDateString(),
            'currency_id' => $currencyId,
            'exchange_rate' => 1,
            'notes' => 'Test draft invoice',
            'idempotency_key' => 'inv-'.fake()->uuid(),
            'lines' => [
                [
                    'description' => 'Consulting services',
                    'quantity' => 2,
                    'unit_price' => 100,
                    'discount_amount' => 0,
                    'revenue_account_id' => $revenueAccountId,
                    'tax_code_id' => $taxCodeId,
                ],
            ],
        ];
    }

    public function test_index_requires_authentication(): void
    {
        $this->get('/invoices')->assertRedirect(route('login'));
    }

    public function test_index_forbids_user_without_permission(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $this->attachRoleWithPermission($user, $org, []);

        $this->actingAs($user)
            ->get('/invoices')
            ->assertStatus(403);
    }

    public function test_index_allows_user_with_permission(): void
    {
        $user = User::factory()->create();
        [$org] = $this->invoiceOrganization();
        $this->attachRoleWithPermission($user, $org, 'view-invoices');

        $this->actingAs($user)
            ->get('/invoices')
            ->assertOk();
    }

    public function test_store_forbids_user_without_permission(): void
    {
        $user = User::factory()->create();
        [$org, $revenue, $taxCode, $customer, $currencyId] = $this->invoiceOrganization();
        $this->attachRoleWithPermission($user, $org, []);

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/invoices', $this->storePayload($revenue->id, $taxCode->id, $customer->id, $currencyId))
            ->assertStatus(403);
    }

    public function test_store_creates_draft_with_totals_and_audit(): void
    {
        $user = User::factory()->create();
        [$org, $revenue, $taxCode, $customer, $currencyId] = $this->invoiceOrganization();
        $this->attachRoleWithPermission($user, $org, 'create-invoices');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/invoices', $this->storePayload($revenue->id, $taxCode->id, $customer->id, $currencyId))
            ->assertRedirect()
            ->assertSessionHas('success');

        $invoice = CustomerInvoice::query()
            ->where('organization_id', $org->id)
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        $this->assertSame(CustomerInvoiceStatus::Draft, $invoice->status);
        $this->assertSame(200.0, (float) $invoice->subtotal);
        $this->assertSame(24.0, (float) $invoice->tax_amount);
        $this->assertSame(224.0, (float) $invoice->total_amount);
        $this->assertSame(1, $invoice->lines()->count());
        $this->assertNull($invoice->journal_entry_id);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'customer-invoice.created',
            'auditable_type' => CustomerInvoice::class,
            'auditable_id' => $invoice->id,
        ]);
    }

    public function test_store_requires_fields(): void
    {
        $user = User::factory()->create();
        [$org, , $taxCode, $customer, $currencyId] = $this->invoiceOrganization();
        $this->attachRoleWithPermission($user, $org, 'create-invoices');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/invoices', [])
            ->assertRedirect()
            ->assertSessionHasErrors(['customer_id', 'invoice_date', 'due_date', 'currency_id', 'lines']);
    }

    public function test_store_rejects_cross_organization_customer(): void
    {
        $user = User::factory()->create();
        [$org, $revenue, $taxCode, , $currencyId] = $this->invoiceOrganization();
        $otherOrg = Organization::factory()->create();
        $otherReceivable = Account::factory()->create(['organization_id' => $otherOrg->id]);
        $otherCustomer = Customer::factory()->create([
            'organization_id' => $otherOrg->id,
            'receivable_account_id' => $otherReceivable->id,
        ]);
        $this->attachRoleWithPermission($user, $org, 'create-invoices');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/invoices', $this->storePayload($revenue->id, $taxCode->id, $otherCustomer->id, $currencyId))
            ->assertRedirect()
            ->assertSessionHas('error', 'The customer must belong to the same organization.');
    }

    // ─── Posting Tests ────────────────────────────────────────────────────

    private function postingSetup(): array
    {
        [$org, $revenue, $taxCode, $customer, $currencyId] = $this->invoiceOrganization();

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

    private function createDraftInvoice(
        Organization $org,
        User $creator,
        int $revenueAccountId,
        int $customerId,
        int $currencyId,
    ): CustomerInvoice {
        $invoice = CustomerInvoice::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customerId,
            'created_by' => $creator->id,
            'currency_id' => $currencyId,
            'base_currency_id' => $org->base_currency_id,
            'status' => CustomerInvoiceStatus::Draft,
            'invoice_date' => today()->toDateString(),
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 100,
            'paid_amount' => 0,
            'balance_due' => 100,
        ]);

        CustomerInvoiceLine::create([
            'organization_id' => $org->id,
            'invoice_id' => $invoice->id,
            'line_number' => 1,
            'description' => 'Test service',
            'quantity' => 1,
            'unit_price' => 100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'line_total' => 100,
            'revenue_account_id' => $revenueAccountId,
            'currency_id' => $currencyId,
        ]);

        return $invoice;
    }

    public function test_post_issues_draft_invoice_and_creates_journal_entry(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, $revenue, , $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-invoices']);
        $this->attachRoleWithPermission($poster, $org, 'approve-invoices');

        $invoice = $this->createDraftInvoice($org, $creator, $revenue->id, $customer->id, $currencyId);

        $this->actingAs($poster)
            ->post("/invoices/{$invoice->id}/post")
            ->assertRedirect()
            ->assertSessionHas('success', 'Invoice posted to ledger.');

        $invoice->refresh();
        $this->assertSame(CustomerInvoiceStatus::Posted, $invoice->status);
        $this->assertNotNull($invoice->journal_entry_id);
        $this->assertDatabaseHas('journal_entries', [
            'organization_id' => $org->id,
            'status' => 'posted',
        ]);
    }

    public function test_post_forbids_creator_from_posting_own_invoice(): void
    {
        $user = User::factory()->create();
        [$org, $revenue, , $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, ['create-invoices', 'approve-invoices']);

        $invoice = $this->createDraftInvoice($org, $user, $revenue->id, $customer->id, $currencyId);

        $this->actingAs($user)
            ->post("/invoices/{$invoice->id}/post")
            ->assertRedirect()
            ->assertSessionHas('error', 'Maker-checker: The final poster cannot be the same person who created the invoice.');

        $this->assertSame(CustomerInvoiceStatus::Draft, $invoice->fresh()->status);
    }

    public function test_post_forbids_user_without_permission(): void
    {
        $creator = User::factory()->create();
        $user = User::factory()->create();
        [$org, $revenue, , $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, 'create-invoices');
        $this->attachRoleWithPermission($user, $org, []);

        $invoice = $this->createDraftInvoice($org, $creator, $revenue->id, $customer->id, $currencyId);

        $this->actingAs($user)
            ->post("/invoices/{$invoice->id}/post")
            ->assertStatus(403);
    }

    public function test_post_returns_404_for_cross_organization_invoice(): void
    {
        $creator = User::factory()->create();
        [$orgA, $revenueA, , $customerA, $currencyIdA] = $this->postingSetup();
        $orgB = Organization::factory()->create();
        $this->attachRoleWithPermission($creator, $orgA, 'create-invoices');
        $this->attachRoleWithPermission($creator, $orgB, 'approve-invoices');

        $invoice = $this->createDraftInvoice($orgA, $creator, $revenueA->id, $customerA->id, $currencyIdA);

        // Switch session to orgB, then try to post orgA's invoice
        session(['current_organization_id' => $orgB->id]);

        $this->actingAs($creator)
            ->post("/invoices/{$invoice->id}/post")
            ->assertStatus(404);
    }

    public function test_post_returns_error_when_no_organization_selected(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $receivable = Account::factory()->create(['organization_id' => $org->id]);
        $customer = Customer::factory()->create([
            'organization_id' => $org->id,
            'receivable_account_id' => $receivable->id,
        ]);

        $invoice = CustomerInvoice::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'created_by' => User::factory()->create()->id,
            'status' => CustomerInvoiceStatus::Draft,
        ]);

        $this->actingAs($user)
            ->post("/invoices/{$invoice->id}/post")
            ->assertRedirect()
            ->assertSessionHas('error', 'No organization selected.');
    }

    public function test_post_returns_generic_error_on_query_exception(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, $revenue, , $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($creator, $org, ['create-invoices']);
        $this->attachRoleWithPermission($poster, $org, 'approve-invoices');

        $invoice = $this->createDraftInvoice($org, $creator, $revenue->id, $customer->id, $currencyId);

        // Drop a column the posting flow inserts into, forcing a query exception inside PostCustomerInvoice.
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn(['description']);
        });

        $this->actingAs($poster)
            ->post("/invoices/{$invoice->id}/post")
            ->assertRedirect()
            ->assertSessionHas('error', 'Unable to post the invoice. Please try again.');
    }

    // ─── Void Tests ───────────────────────────────────────────────────────

    public function test_void_draft_invoice_with_reason(): void
    {
        $user = User::factory()->create();
        [$org, $revenue, , $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, 'void-invoices');

        $invoice = $this->createDraftInvoice($org, $user, $revenue->id, $customer->id, $currencyId);

        $this->actingAs($user)
            ->post("/invoices/{$invoice->id}/void", ['reason' => 'Changed my mind'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Invoice voided.');

        $invoice->refresh();
        $this->assertSame(CustomerInvoiceStatus::Voided, $invoice->status);
        $this->assertSame('Changed my mind', $invoice->void_reason);
        $this->assertNotNull($invoice->voided_at);
    }

    public function test_void_requires_reason(): void
    {
        $user = User::factory()->create();
        [$org, $revenue, , $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, 'void-invoices');

        $invoice = $this->createDraftInvoice($org, $user, $revenue->id, $customer->id, $currencyId);

        $this->actingAs($user)
            ->post("/invoices/{$invoice->id}/void", [])
            ->assertRedirect()
            ->assertSessionHasErrors(['reason']);
    }

    public function test_void_forbids_user_without_permission(): void
    {
        $user = User::factory()->create();
        [$org, $revenue, , $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, []);

        $invoice = $this->createDraftInvoice($org, $user, $revenue->id, $customer->id, $currencyId);

        $this->actingAs($user)
            ->post("/invoices/{$invoice->id}/void", ['reason' => 'test'])
            ->assertStatus(403);
    }

    public function test_void_rejects_non_draft_invoice(): void
    {
        $user = User::factory()->create();
        [$org, $revenue, , $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, 'void-invoices');

        $invoice = CustomerInvoice::factory()->posted()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'created_by' => User::factory()->create()->id,
            'currency_id' => $currencyId,
            'base_currency_id' => $org->base_currency_id,
        ]);

        $this->actingAs($user)
            ->post("/invoices/{$invoice->id}/void", ['reason' => 'Too late'])
            ->assertRedirect()
            ->assertSessionHas('error', 'Only draft invoices can be voided.');
    }

    public function test_void_returns_404_for_cross_organization_invoice(): void
    {
        $user = User::factory()->create();
        [$orgA, $revenueA, , $customerA, $currencyIdA] = $this->postingSetup();
        $orgB = Organization::factory()->create();
        $this->attachRoleWithPermission($user, $orgA, 'void-invoices');
        $this->attachRoleWithPermission($user, $orgB, 'void-invoices');

        $invoice = $this->createDraftInvoice($orgA, $user, $revenueA->id, $customerA->id, $currencyIdA);

        session(['current_organization_id' => $orgB->id]);

        $this->actingAs($user)
            ->post("/invoices/{$invoice->id}/void", ['reason' => 'test'])
            ->assertStatus(404);
    }

    public function test_void_returns_error_when_no_organization_selected(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $receivable = Account::factory()->create(['organization_id' => $org->id]);
        $customer = Customer::factory()->create([
            'organization_id' => $org->id,
            'receivable_account_id' => $receivable->id,
        ]);
        $revenue = Account::factory()->create(['organization_id' => $org->id]);
        $this->attachRoleWithPermission($user, $org, 'void-invoices');

        $invoice = $this->createDraftInvoice($org, $user, $revenue->id, $customer->id, $org->base_currency_id);

        // Detach from org so current returns null
        $user->organizations()->detach();

        $this->actingAs($user)
            ->post("/invoices/{$invoice->id}/void", ['reason' => 'test'])
            ->assertRedirect()
            ->assertSessionHas('error', 'No organization selected.');
    }

    // ─── Idempotency Tests ────────────────────────────────────────────────

    public function test_store_replays_idempotent_request(): void
    {
        $user = User::factory()->create();
        [$org, $revenue, $taxCode, $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, 'create-invoices');

        $this->actingAs($user);
        $this->confirmPassword();

        $payload = $this->storePayload($revenue->id, $taxCode->id, $customer->id, $currencyId);

        // First submission creates the invoice
        $this->post('/invoices', $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $firstInvoice = CustomerInvoice::where('idempotency_key', $payload['idempotency_key'])->firstOrFail();

        // Second submission with the same idempotency key returns the same invoice
        $this->post('/invoices', $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $secondInvoice = CustomerInvoice::where('idempotency_key', $payload['idempotency_key'])->first();

        $this->assertSame($firstInvoice->id, $secondInvoice->id);
        $this->assertDatabaseCount('customer_invoice_lines', 1);
    }

    // ─── Sequence Tests ───────────────────────────────────────────────────

    public function test_invoice_numbers_increment_monotonically(): void
    {
        $user = User::factory()->create();
        [$org, $revenue, $taxCode, $customer, $currencyId] = $this->postingSetup();
        $this->attachRoleWithPermission($user, $org, 'create-invoices');

        $this->actingAs($user);
        $this->confirmPassword();

        $payload1 = $this->storePayload($revenue->id, $taxCode->id, $customer->id, $currencyId);
        $payload2 = $this->storePayload($revenue->id, $taxCode->id, $customer->id, $currencyId);

        $this->post('/invoices', $payload1)->assertSessionHas('success');
        $this->post('/invoices', $payload2)->assertSessionHas('success');

        $first = CustomerInvoice::where('idempotency_key', $payload1['idempotency_key'])->firstOrFail();
        $second = CustomerInvoice::where('idempotency_key', $payload2['idempotency_key'])->firstOrFail();

        $this->assertMatchesRegularExpression('/^INV-\d{6}$/', $first->invoice_number);
        $this->assertMatchesRegularExpression('/^INV-\d{6}$/', $second->invoice_number);

        $firstNum = (int) substr($first->invoice_number, -6);
        $secondNum = (int) substr($second->invoice_number, -6);
        $this->assertSame($firstNum + 1, $secondNum);
    }
}
