<?php

namespace Tests\Feature;

use App\Enums\AccountingPeriodStatus;
use App\Enums\CustomerInvoiceStatus;
use App\Enums\CustomerPaymentStatus;
use App\Enums\FiscalYearStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CustomerPaymentAllocationTest extends TestCase
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

    private function allocationSetup(): array
    {
        $org = Organization::factory()->create();
        $receivable = Account::factory()->create(['organization_id' => $org->id]);
        $cash = Account::factory()->create(['organization_id' => $org->id]);
        $customer = Customer::factory()->create([
            'organization_id' => $org->id,
            'receivable_account_id' => $receivable->id,
        ]);
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

        return [$org, $receivable, $cash, $customer, $org->base_currency_id, $fiscalYear, $period];
    }

    private function draftPayment(
        User $creator,
        Organization $org,
        Customer $customer,
        int $currencyId,
        float $amount = 100,
        ?int $cashAccountId = null,
    ): CustomerPayment {
        return CustomerPayment::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'created_by' => $creator->id,
            'currency_id' => $currencyId,
            'base_currency_id' => $org->base_currency_id,
            'status' => CustomerPaymentStatus::Draft,
            'payment_date' => today()->toDateString(),
            'amount' => $amount,
            'cash_account_id' => $cashAccountId,
        ]);
    }

    private function postedInvoice(
        User $creator,
        Organization $org,
        Customer $customer,
        float $balance,
        ?int $currencyId = null,
    ): CustomerInvoice {
        return CustomerInvoice::factory()->posted()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'currency_id' => $currencyId ?? $org->base_currency_id,
            'base_currency_id' => $org->base_currency_id,
            'subtotal' => $balance,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $balance,
            'paid_amount' => 0,
            'balance_due' => $balance,
            'created_by' => $creator->id,
        ]);
    }

    /**
     * @return array<string, array<int, array{invoice_id: int, amount: float}>>
     */
    private function payload(CustomerInvoice $invoice, float $amount): array
    {
        return ['allocations' => [['invoice_id' => $invoice->id, 'amount' => $amount]]];
    }

    // ─── Authorization ────────────────────────────────────────────────────

    public function test_allocate_requires_authentication(): void
    {
        [$org, , , $customer, $currencyId] = $this->allocationSetup();
        $creator = User::factory()->create();
        $payment = $this->draftPayment($creator, $org, $customer, $currencyId);

        $this->post("/payments/{$payment->id}/allocate", ['allocations' => []])
            ->assertRedirect(route('login'));
    }

    public function test_allocate_forbids_user_without_permission(): void
    {
        [$org, , , $customer, $currencyId] = $this->allocationSetup();
        $creator = User::factory()->create();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, []);

        $payment = $this->draftPayment($creator, $org, $customer, $currencyId);
        $invoice = $this->postedInvoice($creator, $org, $customer, 60);

        $this->actingAs($user)
            ->post("/payments/{$payment->id}/allocate", $this->payload($invoice, 60))
            ->assertStatus(403);
    }

    // ─── Allocation behaviour ─────────────────────────────────────────────

    public function test_allocate_applies_payment_to_multiple_invoices(): void
    {
        [$org, , , $customer, $currencyId] = $this->allocationSetup();
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $this->attachRoleWithPermission($allocator, $org, 'allocate-payments');

        $payment = $this->draftPayment($creator, $org, $customer, $currencyId, 100);
        $invoiceA = $this->postedInvoice($creator, $org, $customer, 60);
        $invoiceB = $this->postedInvoice($creator, $org, $customer, 40);

        $this->actingAs($allocator)
            ->post('/payments/'.$payment->id.'/allocate', ['allocations' => [
                ['invoice_id' => $invoiceA->id, 'amount' => 60],
                ['invoice_id' => $invoiceB->id, 'amount' => 40],
            ]])
            ->assertRedirect()
            ->assertSessionHas('success', 'Payment allocation saved.');

        $this->assertDatabaseHas('customer_payment_allocations', [
            'organization_id' => $org->id,
            'payment_id' => $payment->id,
            'invoice_id' => $invoiceA->id,
            'amount' => 60,
        ]);
        $this->assertDatabaseHas('customer_payment_allocations', [
            'organization_id' => $org->id,
            'payment_id' => $payment->id,
            'invoice_id' => $invoiceB->id,
            'amount' => 40,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'customer-payment.allocated',
            'auditable_type' => CustomerPayment::class,
            'auditable_id' => $payment->id,
        ]);
    }

    public function test_allocate_rejects_amount_beyond_payment(): void
    {
        [$org, , , $customer, $currencyId] = $this->allocationSetup();
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $this->attachRoleWithPermission($allocator, $org, 'allocate-payments');

        $payment = $this->draftPayment($creator, $org, $customer, $currencyId, 100);
        $invoice = $this->postedInvoice($creator, $org, $customer, 60);

        $this->actingAs($allocator)
            ->post("/payments/{$payment->id}/allocate", $this->payload($invoice, 120))
            ->assertRedirect()
            ->assertSessionHas('error', 'Payment '.$payment->payment_number.' cannot be allocated beyond its available amount.');
    }

    public function test_allocate_rejects_amount_beyond_invoice_balance(): void
    {
        [$org, , , $customer, $currencyId] = $this->allocationSetup();
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $this->attachRoleWithPermission($allocator, $org, 'allocate-payments');

        $payment = $this->draftPayment($creator, $org, $customer, $currencyId, 100);
        $invoice = $this->postedInvoice($creator, $org, $customer, 60);

        $this->actingAs($allocator)
            ->post("/payments/{$payment->id}/allocate", $this->payload($invoice, 80))
            ->assertRedirect()
            ->assertSessionHas('error', 'Invoice '.$invoice->invoice_number.' cannot be allocated beyond its outstanding balance.');
    }

    public function test_allocate_rejects_invoice_of_another_customer(): void
    {
        [$org, , , $customer, $currencyId] = $this->allocationSetup();
        $otherReceivable = Account::factory()->create(['organization_id' => $org->id]);
        $otherCustomer = Customer::factory()->create([
            'organization_id' => $org->id,
            'receivable_account_id' => $otherReceivable->id,
        ]);
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $this->attachRoleWithPermission($allocator, $org, 'allocate-payments');

        $payment = $this->draftPayment($creator, $org, $customer, $currencyId, 100);
        $invoice = $this->postedInvoice($creator, $org, $otherCustomer, 60);

        $this->actingAs($allocator)
            ->post("/payments/{$payment->id}/allocate", $this->payload($invoice, 60))
            ->assertRedirect()
            ->assertSessionHas('error', 'The invoice must belong to the same organization and customer.');
    }

    public function test_allocate_rejects_cross_organization_invoice(): void
    {
        [$org, , , $customer, $currencyId] = $this->allocationSetup();
        $otherOrg = Organization::factory()->create();
        $otherReceivable = Account::factory()->create(['organization_id' => $otherOrg->id]);
        $otherCustomer = Customer::factory()->create([
            'organization_id' => $otherOrg->id,
            'receivable_account_id' => $otherReceivable->id,
        ]);
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $this->attachRoleWithPermission($allocator, $org, 'allocate-payments');

        $payment = $this->draftPayment($creator, $org, $customer, $currencyId, 100);
        $invoice = $this->postedInvoice($creator, $otherOrg, $otherCustomer, 60);

        $this->actingAs($allocator)
            ->post("/payments/{$payment->id}/allocate", $this->payload($invoice, 60))
            ->assertRedirect()
            ->assertSessionHas('error', 'The invoice must belong to the same organization and customer.');
    }

    public function test_allocate_rejects_unposted_invoice(): void
    {
        [$org, , , $customer, $currencyId] = $this->allocationSetup();
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $this->attachRoleWithPermission($allocator, $org, 'allocate-payments');

        $payment = $this->draftPayment($creator, $org, $customer, $currencyId, 100);
        $invoice = CustomerInvoice::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'currency_id' => $org->base_currency_id,
            'base_currency_id' => $org->base_currency_id,
            'total_amount' => 60,
            'paid_amount' => 0,
            'balance_due' => 60,
            'status' => CustomerInvoiceStatus::Draft,
            'created_by' => $creator->id,
        ]);

        $this->actingAs($allocator)
            ->post("/payments/{$payment->id}/allocate", $this->payload($invoice, 60))
            ->assertRedirect()
            ->assertSessionHas('error', 'Only posted invoices can be allocated.');
    }

    public function test_allocate_rejects_currency_mismatch(): void
    {
        [$org, , , $customer, $currencyId] = $this->allocationSetup();
        $otherCurrency = Currency::factory()->create();
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $this->attachRoleWithPermission($allocator, $org, 'allocate-payments');

        $payment = $this->draftPayment($creator, $org, $customer, $currencyId, 100);
        $invoice = $this->postedInvoice($creator, $org, $customer, 60, $otherCurrency->id);

        $this->actingAs($allocator)
            ->post("/payments/{$payment->id}/allocate", $this->payload($invoice, 60))
            ->assertRedirect()
            ->assertSessionHas('error', 'The payment and the invoice must use the same currency.');
    }

    public function test_allocate_rejects_posted_payment(): void
    {
        [$org, , $cash, $customer, $currencyId] = $this->allocationSetup();
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $this->attachRoleWithPermission($allocator, $org, 'allocate-payments');

        $payment = $this->draftPayment($creator, $org, $customer, $currencyId, 100, $cash->id);
        $payment->update(['status' => CustomerPaymentStatus::Posted]);
        $invoice = $this->postedInvoice($creator, $org, $customer, 60);

        $this->actingAs($allocator)
            ->post("/payments/{$payment->id}/allocate", $this->payload($invoice, 60))
            ->assertRedirect()
            ->assertSessionHas('error', 'Only draft payments can be allocated.');
    }

    public function test_allocate_is_cumulative_and_prevents_duplicate_submit(): void
    {
        [$org, , , $customer, $currencyId] = $this->allocationSetup();
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $this->attachRoleWithPermission($allocator, $org, 'allocate-payments');

        $payment = $this->draftPayment($creator, $org, $customer, $currencyId, 100);
        $invoiceA = $this->postedInvoice($creator, $org, $customer, 40);
        $invoiceB = $this->postedInvoice($creator, $org, $customer, 60);

        $this->actingAs($allocator)->post("/payments/{$payment->id}/allocate", $this->payload($invoiceA, 40))->assertSessionHas('success');
        $this->actingAs($allocator)->post("/payments/{$payment->id}/allocate", $this->payload($invoiceB, 60))->assertSessionHas('success');

        $this->assertSame(100.0, (float) $payment->fresh()->totalAllocated());

        // A duplicate re-submit must fail safely instead of double-allocating.
        $this->actingAs($allocator)
            ->post("/payments/{$payment->id}/allocate", $this->payload($invoiceA, 10))
            ->assertRedirect()
            ->assertSessionHas('error', 'Payment '.$payment->payment_number.' cannot be allocated beyond its available amount.');

        $this->assertSame(100.0, (float) $payment->fresh()->totalAllocated());
    }

    public function test_allocate_returns_404_for_cross_organization_payment(): void
    {
        [$orgA, , , $customerA, $currencyIdA] = $this->allocationSetup();
        $orgB = Organization::factory()->create();
        $creator = User::factory()->create();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($creator, $orgA, 'allocate-payments');
        $this->attachRoleWithPermission($user, $orgB, 'allocate-payments');

        $payment = $this->draftPayment($creator, $orgA, $customerA, $currencyIdA);
        $invoice = $this->postedInvoice($creator, $orgA, $customerA, 60);

        session(['current_organization_id' => $orgB->id]);

        $this->actingAs($user)
            ->post("/payments/{$payment->id}/allocate", $this->payload($invoice, 60))
            ->assertStatus(404);
    }

    public function test_index_exposes_allocable_invoices_for_draft_payments(): void
    {
        [$org, , , $customer, $currencyId] = $this->allocationSetup();
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $this->attachRoleWithPermission($allocator, $org, ['view-payments', 'allocate-payments']);

        $payment = $this->draftPayment($creator, $org, $customer, $currencyId, 100);
        $invoice = $this->postedInvoice($creator, $org, $customer, 60);

        $this->actingAs($allocator)
            ->get('/payments')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Payments/Index')
                ->where('payments.data.0.can.allocate', true)
                ->where('payments.data.0.allocable_invoices.0.id', $invoice->id)
                ->where('payments.data.0.allocable_invoices.0.available', 60));
    }

    // ─── Reconcile on post ────────────────────────────────────────────────

    public function test_post_reconciles_fully_settled_invoices_to_paid(): void
    {
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, $receivable, $cash, $customer, $currencyId] = $this->allocationSetup();
        $this->attachRoleWithPermission($allocator, $org, 'allocate-payments');
        $this->attachRoleWithPermission($poster, $org, 'approve-payments');

        $payment = $this->draftPayment($creator, $org, $customer, $currencyId, 100, $cash->id);
        $invoiceA = $this->postedInvoice($creator, $org, $customer, 60);
        $invoiceB = $this->postedInvoice($creator, $org, $customer, 40);

        $this->actingAs($allocator)
            ->post('/payments/'.$payment->id.'/allocate', ['allocations' => [
                ['invoice_id' => $invoiceA->id, 'amount' => 60],
                ['invoice_id' => $invoiceB->id, 'amount' => 40],
            ]])
            ->assertSessionHas('success');

        $this->actingAs($poster)
            ->post("/payments/{$payment->id}/post")
            ->assertRedirect()
            ->assertSessionHas('success', 'Payment posted to ledger.');

        $this->assertSame(CustomerInvoiceStatus::Paid, $invoiceA->fresh()->status);
        $this->assertSame(CustomerInvoiceStatus::Paid, $invoiceB->fresh()->status);
        $this->assertSame(60.0, (float) $invoiceA->fresh()->paid_amount);
        $this->assertSame(0.0, (float) $invoiceA->fresh()->balance_due);
        $this->assertSame(40.0, (float) $invoiceB->fresh()->paid_amount);
        $this->assertSame(0.0, (float) $invoiceB->fresh()->balance_due);

        $this->assertDatabaseHas('journal_entries', [
            'organization_id' => $org->id,
            'status' => 'posted',
            'reference' => $payment->payment_number,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'customer-invoice.allocated',
            'auditable_type' => CustomerInvoice::class,
            'auditable_id' => $invoiceA->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'customer-invoice.allocated',
            'auditable_type' => CustomerInvoice::class,
            'auditable_id' => $invoiceB->id,
        ]);

        $receivableBalance = $payment->fresh('journalEntry.lines')->journalEntry->lines
            ->where('account_id', $receivable->id)
            ->sum('credit');
        $this->assertSame(100.0, (float) $receivableBalance);
    }

    public function test_post_reconciles_partial_allocation_to_partially_paid(): void
    {
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, , $cash, $customer, $currencyId] = $this->allocationSetup();
        $this->attachRoleWithPermission($allocator, $org, 'allocate-payments');
        $this->attachRoleWithPermission($poster, $org, 'approve-payments');

        $payment = $this->draftPayment($creator, $org, $customer, $currencyId, 100, $cash->id);
        $invoice = $this->postedInvoice($creator, $org, $customer, 100);

        $this->actingAs($allocator)->post("/payments/{$payment->id}/allocate", $this->payload($invoice, 40))->assertSessionHas('success');

        $this->actingAs($poster)->post("/payments/{$payment->id}/post")->assertSessionHas('success');

        $this->assertSame(CustomerInvoiceStatus::PartiallyPaid, $invoice->fresh()->status);
        $this->assertSame(40.0, (float) $invoice->fresh()->paid_amount);
        $this->assertSame(60.0, (float) $invoice->fresh()->balance_due);
    }

    public function test_post_without_allocations_leaves_invoice_untouched(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, , $cash, $customer, $currencyId] = $this->allocationSetup();
        $this->attachRoleWithPermission($poster, $org, 'approve-payments');

        $payment = $this->draftPayment($creator, $org, $customer, $currencyId, 100, $cash->id);
        $invoice = $this->postedInvoice($creator, $org, $customer, 100);

        $this->actingAs($poster)->post("/payments/{$payment->id}/post")->assertSessionHas('success');

        $this->assertSame(CustomerInvoiceStatus::Posted, $invoice->fresh()->status);
        $this->assertSame(0.0, (float) $invoice->fresh()->paid_amount);
        $this->assertSame(100.0, (float) $invoice->fresh()->balance_due);
        $this->assertSame(0, CustomerPaymentAllocation::count());
    }

    public function test_two_draft_payments_cannot_over_allocate_one_invoice(): void
    {
        [$org, , , $customer, $currencyId] = $this->allocationSetup();
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $this->attachRoleWithPermission($allocator, $org, 'allocate-payments');

        $invoice = $this->postedInvoice($creator, $org, $customer, 100);
        $paymentA = $this->draftPayment($creator, $org, $customer, $currencyId, 100);
        $paymentB = $this->draftPayment($creator, $org, $customer, $currencyId, 100);

        $this->actingAs($allocator)->post("/payments/{$paymentA->id}/allocate", $this->payload($invoice, 100))->assertSessionHas('success');

        $this->actingAs($allocator)
            ->post("/payments/{$paymentB->id}/allocate", $this->payload($invoice, 10))
            ->assertRedirect()
            ->assertSessionHas('error', 'Invoice '.$invoice->invoice_number.' cannot be allocated beyond its outstanding balance.');

        $this->assertSame(100.0, (float) $invoice->fresh()->allocations->sum('amount'));
    }
}
