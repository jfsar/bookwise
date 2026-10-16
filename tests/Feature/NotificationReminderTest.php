<?php

namespace Tests\Feature;

use App\Actions\Notification\SendInvoiceDueReminders;
use App\Actions\Notification\SendPeriodClosingReminders;
use App\Actions\Notification\SendVendorBillDueReminders;
use App\Enums\AccountingPeriodStatus;
use App\Enums\CustomerInvoiceStatus;
use App\Enums\VendorBillStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\FiscalYear;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Notifications\InvoiceDueSoon;
use App\Notifications\InvoiceOverdue;
use App\Notifications\PeriodClosingReminder;
use App\Notifications\VendorBillDue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationReminderTest extends TestCase
{
    use RefreshDatabase;

    private function memberWithPermission(Organization $organization, string $permissionSlug): User
    {
        $user = User::factory()->create();
        $user->organizations()->attach($organization->id, ['membership_status' => 'active']);

        $role = Role::create([
            'name' => 'Staff',
            'slug' => 'staff-'.Str::lower(Str::random(10)),
            'is_global' => false,
        ]);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => $permissionSlug])->id);
        $user->roles()->attach($role->id, ['organization_id' => $organization->id]);

        return $user;
    }

    private function invoice(Organization $organization, string $status, string $dueDate): CustomerInvoice
    {
        $receivable = Account::factory()->create(['organization_id' => $organization->id]);
        $customer = Customer::factory()->create([
            'organization_id' => $organization->id,
            'receivable_account_id' => $receivable->id,
        ]);

        return CustomerInvoice::factory()->create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'status' => CustomerInvoiceStatus::from($status),
            'invoice_date' => Carbon::parse($dueDate)->min(now())->toDateString(),
            'due_date' => $dueDate,
        ]);
    }

    private function bill(Organization $organization, string $status, string $dueDate): VendorBill
    {
        $payable = Account::factory()->create(['organization_id' => $organization->id]);
        $vendor = Vendor::factory()->create([
            'organization_id' => $organization->id,
            'payable_account_id' => $payable->id,
        ]);

        return VendorBill::factory()->create([
            'organization_id' => $organization->id,
            'vendor_id' => $vendor->id,
            'status' => VendorBillStatus::from($status),
            'bill_date' => Carbon::parse($dueDate)->min(now())->toDateString(),
            'due_date' => $dueDate,
        ]);
    }

    public function test_overdue_and_upcoming_invoices_notify_staff_who_can_see_invoices(): void
    {
        $organization = Organization::factory()->create();
        $staff = $this->memberWithPermission($organization, 'view-invoices');
        $auditor = $this->memberWithPermission($organization, 'view-reports');

        $this->invoice($organization, 'posted', now()->subDay()->toDateString());
        $this->invoice($organization, 'posted', now()->addDays(3)->toDateString());

        $sent = (new SendInvoiceDueReminders)->handle();

        $this->assertSame(2, $sent);
        $this->assertSame(0, $auditor->notifications()->count());
        $this->assertSame(1, $staff->notifications()->where('type', InvoiceOverdue::class)->count());
        $this->assertSame(1, $staff->notifications()->where('type', InvoiceDueSoon::class)->count());
    }

    public function test_invoice_reminders_skip_draft_and_already_paid_invoices(): void
    {
        $organization = Organization::factory()->create();
        $this->memberWithPermission($organization, 'view-invoices');

        $this->invoice($organization, 'draft', now()->subDays(5)->toDateString());
        $this->invoice($organization, 'paid', now()->subDays(5)->toDateString());
        $this->invoice($organization, 'posted', now()->addDays(90)->toDateString());

        $this->assertSame(0, (new SendInvoiceDueReminders)->handle());
    }

    public function test_invoice_reminders_are_idempotent_within_the_same_run_day(): void
    {
        $organization = Organization::factory()->create();
        $this->memberWithPermission($organization, 'view-invoices');

        $this->invoice($organization, 'posted', now()->subDay()->toDateString());

        $this->assertSame(1, (new SendInvoiceDueReminders)->handle());
        $this->assertSame(0, (new SendInvoiceDueReminders)->handle());
    }

    public function test_invoice_reminders_do_not_cross_organizations(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $this->memberWithPermission($otherOrganization, 'view-invoices');

        $this->invoice($organization, 'posted', now()->subDay()->toDateString());

        $this->assertSame(0, (new SendInvoiceDueReminders)->handle());
    }

    public function test_vendor_bill_reminders_notify_staff_who_can_see_bills(): void
    {
        $organization = Organization::factory()->create();
        $staff = $this->memberWithPermission($organization, 'view-bills');

        $this->bill($organization, 'posted', now()->addDays(2)->toDateString());
        $this->bill($organization, 'posted', now()->addDays(120)->toDateString());

        $this->assertSame(1, (new SendVendorBillDueReminders)->handle());
        $this->assertSame(1, $staff->notifications()->where('type', VendorBillDue::class)->count());
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $staff->id,
            'type' => VendorBillDue::class,
            'organization_id' => $organization->id,
        ]);
    }

    public function test_vendor_bill_reminders_are_idempotent_within_the_same_run_day(): void
    {
        $organization = Organization::factory()->create();
        $this->memberWithPermission($organization, 'view-bills');

        $this->bill($organization, 'posted', now()->addDays(2)->toDateString());

        $this->assertSame(1, (new SendVendorBillDueReminders)->handle());
        $this->assertSame(0, (new SendVendorBillDueReminders)->handle());
    }

    public function test_period_closing_reminders_notify_staff_who_can_close_periods(): void
    {
        $organization = Organization::factory()->create();
        $staff = $this->memberWithPermission($organization, 'close-accounting-period');

        $fiscalYear = FiscalYear::factory()->create(['organization_id' => $organization->id]);
        AccountingPeriod::factory()->create([
            'organization_id' => $organization->id,
            'fiscal_year_id' => $fiscalYear->id,
            'period_number' => 1,
            'status' => AccountingPeriodStatus::Open,
            'end_date' => now()->addDays(3)->toDateString(),
        ]);

        AccountingPeriod::factory()->create([
            'organization_id' => $organization->id,
            'fiscal_year_id' => $fiscalYear->id,
            'period_number' => 2,
            'status' => AccountingPeriodStatus::Closed,
            'end_date' => now()->addDays(4)->toDateString(),
            'closed_at' => now(),
        ]);

        $this->assertSame(1, (new SendPeriodClosingReminders)->handle());
        $this->assertSame(1, $staff->notifications()->where('type', PeriodClosingReminder::class)->count());
    }

    public function test_period_closing_reminders_do_not_reopen_or_close_a_period(): void
    {
        $organization = Organization::factory()->create();
        $this->memberWithPermission($organization, 'close-accounting-period');

        $fiscalYear = FiscalYear::factory()->create(['organization_id' => $organization->id]);
        $period = AccountingPeriod::factory()->create([
            'organization_id' => $organization->id,
            'fiscal_year_id' => $fiscalYear->id,
            'status' => AccountingPeriodStatus::Open,
            'end_date' => now()->addDays(3)->toDateString(),
        ]);

        (new SendPeriodClosingReminders)->handle();

        $this->assertTrue($period->fresh()->isOpen());
        $this->assertNull($period->fresh()->closed_at);
    }
}
