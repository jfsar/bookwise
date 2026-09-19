<?php

namespace Tests\Feature;

use App\Enums\AccountingPeriodStatus;
use App\Enums\FiscalYearStatus;
use App\Enums\VendorBillStatus;
use App\Enums\VendorPaymentStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorPayment;
use App\Models\VendorPaymentAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class VendorPaymentAllocationTest extends TestCase
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
        $payable = Account::factory()->create(['organization_id' => $org->id]);
        $cash = Account::factory()->create(['organization_id' => $org->id]);
        $vendor = Vendor::factory()->create([
            'organization_id' => $org->id,
            'payable_account_id' => $payable->id,
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

        return [$org, $payable, $cash, $vendor, $org->base_currency_id, $fiscalYear, $period];
    }

    private function draftPayment(
        User $creator,
        Organization $org,
        Vendor $vendor,
        int $currencyId,
        float $amount = 100,
        ?int $cashAccountId = null,
    ): VendorPayment {
        return VendorPayment::factory()->create([
            'organization_id' => $org->id,
            'vendor_id' => $vendor->id,
            'created_by' => $creator->id,
            'currency_id' => $currencyId,
            'base_currency_id' => $org->base_currency_id,
            'status' => VendorPaymentStatus::Draft,
            'payment_date' => today()->toDateString(),
            'amount' => $amount,
            'cash_account_id' => $cashAccountId,
        ]);
    }

    private function postedBill(
        User $creator,
        Organization $org,
        Vendor $vendor,
        float $balance,
        ?int $currencyId = null,
    ): VendorBill {
        return VendorBill::factory()->posted()->create([
            'organization_id' => $org->id,
            'vendor_id' => $vendor->id,
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
     * @return array<string, array<int, array{bill_id: int, amount: float}>>
     */
    private function payload(VendorBill $bill, float $amount): array
    {
        return ['allocations' => [['bill_id' => $bill->id, 'amount' => $amount]]];
    }

    // ─── Authorization ────────────────────────────────────────────────────

    public function test_allocate_requires_authentication(): void
    {
        [$org, , , $vendor, $currencyId] = $this->allocationSetup();
        $creator = User::factory()->create();
        $payment = $this->draftPayment($creator, $org, $vendor, $currencyId);

        $this->post("/vendor-payments/{$payment->id}/allocate", ['allocations' => []])
            ->assertRedirect(route('login'));
    }

    public function test_allocate_forbids_user_without_permission(): void
    {
        [$org, , , $vendor, $currencyId] = $this->allocationSetup();
        $creator = User::factory()->create();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, []);

        $payment = $this->draftPayment($creator, $org, $vendor, $currencyId);
        $bill = $this->postedBill($creator, $org, $vendor, 60);

        $this->actingAs($user)
            ->post("/vendor-payments/{$payment->id}/allocate", $this->payload($bill, 60))
            ->assertStatus(403);
    }

    // ─── Allocation behaviour ─────────────────────────────────────────────

    public function test_allocate_applies_payment_to_multiple_bills(): void
    {
        [$org, , , $vendor, $currencyId] = $this->allocationSetup();
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $this->attachRoleWithPermission($allocator, $org, 'allocate-vendor-payments');

        $payment = $this->draftPayment($creator, $org, $vendor, $currencyId, 100);
        $billA = $this->postedBill($creator, $org, $vendor, 60);
        $billB = $this->postedBill($creator, $org, $vendor, 40);

        $this->actingAs($allocator)
            ->post('/vendor-payments/'.$payment->id.'/allocate', ['allocations' => [
                ['bill_id' => $billA->id, 'amount' => 60],
                ['bill_id' => $billB->id, 'amount' => 40],
            ]])
            ->assertRedirect()
            ->assertSessionHas('success', 'Payment allocation saved.');

        $this->assertDatabaseHas('vendor_payment_allocations', [
            'organization_id' => $org->id,
            'payment_id' => $payment->id,
            'bill_id' => $billA->id,
            'amount' => 60,
        ]);
        $this->assertDatabaseHas('vendor_payment_allocations', [
            'organization_id' => $org->id,
            'payment_id' => $payment->id,
            'bill_id' => $billB->id,
            'amount' => 40,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'vendor-payment.allocated',
            'auditable_type' => VendorPayment::class,
            'auditable_id' => $payment->id,
        ]);
    }

    public function test_allocate_rejects_amount_beyond_payment(): void
    {
        [$org, , , $vendor, $currencyId] = $this->allocationSetup();
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $this->attachRoleWithPermission($allocator, $org, 'allocate-vendor-payments');

        $payment = $this->draftPayment($creator, $org, $vendor, $currencyId, 100);
        $bill = $this->postedBill($creator, $org, $vendor, 60);

        $this->actingAs($allocator)
            ->post("/vendor-payments/{$payment->id}/allocate", $this->payload($bill, 120))
            ->assertRedirect()
            ->assertSessionHas('error', 'Payment '.$payment->payment_number.' cannot be allocated beyond its available amount.');
    }

    public function test_allocate_rejects_amount_beyond_bill_balance(): void
    {
        [$org, , , $vendor, $currencyId] = $this->allocationSetup();
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $this->attachRoleWithPermission($allocator, $org, 'allocate-vendor-payments');

        $payment = $this->draftPayment($creator, $org, $vendor, $currencyId, 100);
        $bill = $this->postedBill($creator, $org, $vendor, 60);

        $this->actingAs($allocator)
            ->post("/vendor-payments/{$payment->id}/allocate", $this->payload($bill, 80))
            ->assertRedirect()
            ->assertSessionHas('error', 'Bill '.$bill->bill_number.' cannot be allocated beyond its outstanding balance.');
    }

    public function test_allocate_rejects_bill_of_another_vendor(): void
    {
        [$org, , , $vendor, $currencyId] = $this->allocationSetup();
        $otherPayable = Account::factory()->create(['organization_id' => $org->id]);
        $otherVendor = Vendor::factory()->create([
            'organization_id' => $org->id,
            'payable_account_id' => $otherPayable->id,
        ]);
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $this->attachRoleWithPermission($allocator, $org, 'allocate-vendor-payments');

        $payment = $this->draftPayment($creator, $org, $vendor, $currencyId, 100);
        $bill = $this->postedBill($creator, $org, $otherVendor, 60);

        $this->actingAs($allocator)
            ->post("/vendor-payments/{$payment->id}/allocate", $this->payload($bill, 60))
            ->assertRedirect()
            ->assertSessionHas('error', 'The bill must belong to the same organization and vendor.');
    }

    public function test_allocate_rejects_cross_organization_bill(): void
    {
        [$org, , , $vendor, $currencyId] = $this->allocationSetup();
        $otherOrg = Organization::factory()->create();
        $otherPayable = Account::factory()->create(['organization_id' => $otherOrg->id]);
        $otherVendor = Vendor::factory()->create([
            'organization_id' => $otherOrg->id,
            'payable_account_id' => $otherPayable->id,
        ]);
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $this->attachRoleWithPermission($allocator, $org, 'allocate-vendor-payments');

        $payment = $this->draftPayment($creator, $org, $vendor, $currencyId, 100);
        $bill = $this->postedBill($creator, $otherOrg, $otherVendor, 60);

        $this->actingAs($allocator)
            ->post("/vendor-payments/{$payment->id}/allocate", $this->payload($bill, 60))
            ->assertRedirect()
            ->assertSessionHas('error', 'The bill must belong to the same organization and vendor.');
    }

    public function test_allocate_rejects_unposted_bill(): void
    {
        [$org, , , $vendor, $currencyId] = $this->allocationSetup();
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $this->attachRoleWithPermission($allocator, $org, 'allocate-vendor-payments');

        $payment = $this->draftPayment($creator, $org, $vendor, $currencyId, 100);
        $bill = VendorBill::factory()->create([
            'organization_id' => $org->id,
            'vendor_id' => $vendor->id,
            'currency_id' => $org->base_currency_id,
            'base_currency_id' => $org->base_currency_id,
            'total_amount' => 60,
            'paid_amount' => 0,
            'balance_due' => 60,
            'status' => VendorBillStatus::Draft,
            'created_by' => $creator->id,
        ]);

        $this->actingAs($allocator)
            ->post("/vendor-payments/{$payment->id}/allocate", $this->payload($bill, 60))
            ->assertRedirect()
            ->assertSessionHas('error', 'Only posted bills can be allocated.');
    }

    public function test_allocate_rejects_currency_mismatch(): void
    {
        [$org, , , $vendor, $currencyId] = $this->allocationSetup();
        $otherCurrency = Currency::factory()->create();
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $this->attachRoleWithPermission($allocator, $org, 'allocate-vendor-payments');

        $payment = $this->draftPayment($creator, $org, $vendor, $currencyId, 100);
        $bill = $this->postedBill($creator, $org, $vendor, 60, $otherCurrency->id);

        $this->actingAs($allocator)
            ->post("/vendor-payments/{$payment->id}/allocate", $this->payload($bill, 60))
            ->assertRedirect()
            ->assertSessionHas('error', 'The payment and the bill must use the same currency.');
    }

    public function test_allocate_rejects_posted_payment(): void
    {
        [$org, , $cash, $vendor, $currencyId] = $this->allocationSetup();
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $this->attachRoleWithPermission($allocator, $org, ['allocate-vendor-payments', 'approve-vendor-payments']);

        $payment = $this->draftPayment($creator, $org, $vendor, $currencyId, 100, $cash->id);
        $payment->update(['status' => VendorPaymentStatus::Posted]);
        $bill = $this->postedBill($creator, $org, $vendor, 60);

        $this->actingAs($allocator)
            ->post("/vendor-payments/{$payment->id}/allocate", $this->payload($bill, 60))
            ->assertRedirect()
            ->assertSessionHas('error', 'Only draft payments can be allocated.');
    }

    public function test_allocate_is_cumulative_and_prevents_duplicate_submit(): void
    {
        [$org, , , $vendor, $currencyId] = $this->allocationSetup();
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $this->attachRoleWithPermission($allocator, $org, 'allocate-vendor-payments');

        $payment = $this->draftPayment($creator, $org, $vendor, $currencyId, 100);
        $billA = $this->postedBill($creator, $org, $vendor, 40);
        $billB = $this->postedBill($creator, $org, $vendor, 60);

        $this->actingAs($allocator)->post("/vendor-payments/{$payment->id}/allocate", $this->payload($billA, 40))->assertSessionHas('success');
        $this->actingAs($allocator)->post("/vendor-payments/{$payment->id}/allocate", $this->payload($billB, 60))->assertSessionHas('success');

        $this->assertSame(100.0, (float) $payment->fresh()->totalAllocated());

        // A duplicate re-submit must fail safely instead of double-allocating.
        $this->actingAs($allocator)
            ->post("/vendor-payments/{$payment->id}/allocate", $this->payload($billA, 10))
            ->assertRedirect()
            ->assertSessionHas('error', 'Payment '.$payment->payment_number.' cannot be allocated beyond its available amount.');

        $this->assertSame(100.0, (float) $payment->fresh()->totalAllocated());
    }

    public function test_allocate_returns_404_for_cross_organization_payment(): void
    {
        [$orgA, , , $vendorA, $currencyIdA] = $this->allocationSetup();
        $orgB = Organization::factory()->create();
        $creator = User::factory()->create();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($creator, $orgA, 'allocate-vendor-payments');
        $this->attachRoleWithPermission($user, $orgB, 'allocate-vendor-payments');

        $payment = $this->draftPayment($creator, $orgA, $vendorA, $currencyIdA);
        $bill = $this->postedBill($creator, $orgA, $vendorA, 60);

        session(['current_organization_id' => $orgB->id]);

        $this->actingAs($user)
            ->post("/vendor-payments/{$payment->id}/allocate", $this->payload($bill, 60))
            ->assertStatus(404);
    }

    public function test_index_exposes_allocable_bills_for_draft_payments(): void
    {
        [$org, , , $vendor, $currencyId] = $this->allocationSetup();
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $this->attachRoleWithPermission($allocator, $org, ['view-vendor-payments', 'allocate-vendor-payments']);

        $payment = $this->draftPayment($creator, $org, $vendor, $currencyId, 100);
        $bill = $this->postedBill($creator, $org, $vendor, 60);

        $this->actingAs($allocator)
            ->get('/vendor-payments')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('VendorPayments/Index')
                ->where('payments.data.0.can.allocate', true)
                ->where('payments.data.0.allocable_bills.0.id', $bill->id)
                ->where('payments.data.0.allocable_bills.0.available', 60));
    }

    // ─── Reconcile on post ────────────────────────────────────────────────

    public function test_post_reconciles_fully_settled_bills_to_paid(): void
    {
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, $payable, $cash, $vendor, $currencyId] = $this->allocationSetup();
        $this->attachRoleWithPermission($allocator, $org, 'allocate-vendor-payments');
        $this->attachRoleWithPermission($poster, $org, 'approve-vendor-payments');

        $payment = $this->draftPayment($creator, $org, $vendor, $currencyId, 100, $cash->id);
        $billA = $this->postedBill($creator, $org, $vendor, 60);
        $billB = $this->postedBill($creator, $org, $vendor, 40);

        $this->actingAs($allocator)
            ->post('/vendor-payments/'.$payment->id.'/allocate', ['allocations' => [
                ['bill_id' => $billA->id, 'amount' => 60],
                ['bill_id' => $billB->id, 'amount' => 40],
            ]])
            ->assertSessionHas('success');

        $this->actingAs($poster)
            ->post("/vendor-payments/{$payment->id}/post")
            ->assertRedirect()
            ->assertSessionHas('success', 'Payment posted to ledger.');

        $this->assertSame(VendorBillStatus::Paid, $billA->fresh()->status);
        $this->assertSame(VendorBillStatus::Paid, $billB->fresh()->status);
        $this->assertSame(60.0, (float) $billA->fresh()->paid_amount);
        $this->assertSame(0.0, (float) $billA->fresh()->balance_due);
        $this->assertSame(40.0, (float) $billB->fresh()->paid_amount);
        $this->assertSame(0.0, (float) $billB->fresh()->balance_due);

        $this->assertDatabaseHas('journal_entries', [
            'organization_id' => $org->id,
            'status' => 'posted',
            'reference' => $payment->payment_number,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'vendor-bill.allocated',
            'auditable_type' => VendorBill::class,
            'auditable_id' => $billA->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'vendor-bill.allocated',
            'auditable_type' => VendorBill::class,
            'auditable_id' => $billB->id,
        ]);

        $payableDebit = $payment->fresh('journalEntry.lines')->journalEntry->lines
            ->where('account_id', $payable->id)
            ->sum('debit');
        $this->assertSame(100.0, (float) $payableDebit);

        $cashCredit = $payment->fresh('journalEntry.lines')->journalEntry->lines
            ->where('account_id', $cash->id)
            ->sum('credit');
        $this->assertSame(100.0, (float) $cashCredit);
    }

    public function test_post_reconciles_partial_allocation_to_partially_paid(): void
    {
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, , $cash, $vendor, $currencyId] = $this->allocationSetup();
        $this->attachRoleWithPermission($allocator, $org, 'allocate-vendor-payments');
        $this->attachRoleWithPermission($poster, $org, 'approve-vendor-payments');

        $payment = $this->draftPayment($creator, $org, $vendor, $currencyId, 100, $cash->id);
        $bill = $this->postedBill($creator, $org, $vendor, 100);

        $this->actingAs($allocator)->post("/vendor-payments/{$payment->id}/allocate", $this->payload($bill, 40))->assertSessionHas('success');

        $this->actingAs($poster)->post("/vendor-payments/{$payment->id}/post")->assertSessionHas('success');

        $this->assertSame(VendorBillStatus::PartiallyPaid, $bill->fresh()->status);
        $this->assertSame(40.0, (float) $bill->fresh()->paid_amount);
        $this->assertSame(60.0, (float) $bill->fresh()->balance_due);
    }

    public function test_post_without_allocations_leaves_bill_untouched(): void
    {
        $creator = User::factory()->create();
        $poster = User::factory()->create();
        [$org, , $cash, $vendor, $currencyId] = $this->allocationSetup();
        $this->attachRoleWithPermission($poster, $org, 'approve-vendor-payments');

        $payment = $this->draftPayment($creator, $org, $vendor, $currencyId, 100, $cash->id);
        $bill = $this->postedBill($creator, $org, $vendor, 100);

        $this->actingAs($poster)->post("/vendor-payments/{$payment->id}/post")->assertSessionHas('success');

        $this->assertSame(VendorBillStatus::Posted, $bill->fresh()->status);
        $this->assertSame(0.0, (float) $bill->fresh()->paid_amount);
        $this->assertSame(100.0, (float) $bill->fresh()->balance_due);
        $this->assertSame(0, VendorPaymentAllocation::count());
    }

    public function test_two_draft_payments_cannot_over_allocate_one_bill(): void
    {
        [$org, , , $vendor, $currencyId] = $this->allocationSetup();
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $this->attachRoleWithPermission($allocator, $org, 'allocate-vendor-payments');

        $bill = $this->postedBill($creator, $org, $vendor, 100);
        $paymentA = $this->draftPayment($creator, $org, $vendor, $currencyId, 100);
        $paymentB = $this->draftPayment($creator, $org, $vendor, $currencyId, 100);

        $this->actingAs($allocator)->post("/vendor-payments/{$paymentA->id}/allocate", $this->payload($bill, 100))->assertSessionHas('success');

        $this->actingAs($allocator)
            ->post("/vendor-payments/{$paymentB->id}/allocate", $this->payload($bill, 10))
            ->assertRedirect()
            ->assertSessionHas('error', 'Bill '.$bill->bill_number.' cannot be allocated beyond its outstanding balance.');

        $this->assertSame(100.0, (float) $bill->fresh('allocations')->allocations->sum('amount'));
    }

    public function test_void_posted_payment_restores_bill_balances(): void
    {
        $creator = User::factory()->create();
        $allocator = User::factory()->create();
        $poster = User::factory()->create();
        $voider = User::factory()->create();
        [$org, , $cash, $vendor, $currencyId] = $this->allocationSetup();
        $this->attachRoleWithPermission($allocator, $org, 'allocate-vendor-payments');
        $this->attachRoleWithPermission($poster, $org, 'approve-vendor-payments');
        $this->attachRoleWithPermission($voider, $org, 'void-vendor-payments');

        $payment = $this->draftPayment($creator, $org, $vendor, $currencyId, 100, $cash->id);
        $bill = $this->postedBill($creator, $org, $vendor, 100);

        $this->actingAs($allocator)->post("/vendor-payments/{$payment->id}/allocate", $this->payload($bill, 60))->assertSessionHas('success');
        $this->actingAs($poster)->post("/vendor-payments/{$payment->id}/post")->assertSessionHas('success');

        $this->assertSame(VendorBillStatus::PartiallyPaid, $bill->fresh()->status);
        $this->assertSame(60.0, (float) $bill->fresh()->paid_amount);

        $this->actingAs($voider)
            ->post("/vendor-payments/{$payment->id}/void", ['reason' => 'Duplicate payment'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Payment voided.');

        $this->assertSame(VendorBillStatus::Posted, $bill->fresh()->status);
        $this->assertSame(0.0, (float) $bill->fresh()->paid_amount);
        $this->assertSame(100.0, (float) $bill->fresh()->balance_due);
    }
}
