<?php

namespace Tests\Feature;

use App\Enums\CustomerInvoiceStatus;
use App\Enums\ReportType;
use App\Jobs\ExportReportJob;
use App\Models\Account;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Reports\ReportCsvExporter;
use App\Reports\ReportFactory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ArAgingReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seeds an organization with an active user, the view-reports permission
     * and a customer, and returns the collectively created records.
     *
     * @return array{org: Organization, user: User, customer: Customer}
     */
    private function seedAging(): array
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);
        session(['current_organization_id' => $org->id]);
        $this->grantPermission($user, $org, 'view-reports');

        $customer = $this->makeCustomer($org, 'CUST-000001', 'Acme Corp');

        return ['org' => $org, 'user' => $user, 'customer' => $customer];
    }

    private function makeCustomer(Organization $org, string $number, string $name): Customer
    {
        $receivable = Account::factory()->create(['organization_id' => $org->id]);

        return Customer::factory()->create([
            'organization_id' => $org->id,
            'customer_number' => $number,
            'name' => $name,
            'receivable_account_id' => $receivable->id,
        ]);
    }

    private function grantPermission(User $user, Organization $org, string $permissionName): void
    {
        $role = Role::create([
            'name' => 'Tester',
            'slug' => 'tester-'.uniqid(),
            'is_global' => false,
        ]);
        $role->permissions()->attach(
            Permission::firstOrCreate(['name' => $permissionName])
        );
        $user->roles()->attach($role->id, ['organization_id' => $org->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function responseProps($response): array
    {
        $page = $response->viewData('page');
        $page = is_string($page) ? json_decode($page, true) : $page;

        return $page['props'];
    }

    /**
     * @param  array{due_date: Carbon, balance_due: int|float}  $invoice
     */
    private function createPostedInvoice(Organization $org, Customer $customer, array $invoice): CustomerInvoice
    {
        return CustomerInvoice::factory()->posted()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'invoice_date' => $invoice['due_date']->copy()->subDays(15)->toDateString(),
            'due_date' => $invoice['due_date']->toDateString(),
            'total_amount' => $invoice['balance_due'],
            'paid_amount' => 0,
            'balance_due' => $invoice['balance_due'],
        ]);
    }

    public function test_page_forbids_users_without_permission(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);
        session(['current_organization_id' => $org->id]);

        $this->actingAs($user)
            ->get('/reports/ar-aging')
            ->assertForbidden();
    }

    public function test_aging_buckets_invoices_by_due_date(): void
    {
        $fixture = $this->seedAging();
        $asOf = Carbon::create(2026, 1, 15);

        $cases = [
            ['days' => -5, 'balance_due' => 500, 'bucket' => 'current'],
            ['days' => 10, 'balance_due' => 100, 'bucket' => 'days_1_30'],
            ['days' => 45, 'balance_due' => 200, 'bucket' => 'days_31_60'],
            ['days' => 75, 'balance_due' => 300, 'bucket' => 'days_61_90'],
            ['days' => 120, 'balance_due' => 400, 'bucket' => 'days_90_plus'],
        ];

        foreach ($cases as $case) {
            $this->createPostedInvoice($fixture['org'], $fixture['customer'], [
                'due_date' => $asOf->copy()->subDays($case['days']),
                'balance_due' => $case['balance_due'],
            ]);
        }

        $props = $this->responseProps(
            $this->actingAs($fixture['user'])
                ->get('/reports/ar-aging?as_of=2026-01-15')
                ->assertOk()
        );

        $this->assertCount(1, $props['data']['rows']);
        $row = $props['data']['rows'][0];
        $this->assertSame(500.0, $row['current']);
        $this->assertSame(100.0, $row['days_1_30']);
        $this->assertSame(200.0, $row['days_31_60']);
        $this->assertSame(300.0, $row['days_61_90']);
        $this->assertSame(400.0, $row['days_90_plus']);
        $this->assertSame(1500.0, $row['total']);
        $this->assertSame($asOf->toDateString(), $props['data']['as_of']);
    }

    public function test_aging_bucket_boundaries(): void
    {
        $fixture = $this->seedAging();
        $asOf = Carbon::create(2026, 3, 1);

        $cases = [
            ['days' => 0, 'bucket' => 'current'],
            ['days' => 1, 'bucket' => 'days_1_30'],
            ['days' => 30, 'bucket' => 'days_1_30'],
            ['days' => 31, 'bucket' => 'days_31_60'],
            ['days' => 60, 'bucket' => 'days_31_60'],
            ['days' => 61, 'bucket' => 'days_61_90'],
            ['days' => 90, 'bucket' => 'days_61_90'],
            ['days' => 91, 'bucket' => 'days_90_plus'],
        ];

        $customers = [];

        foreach ($cases as $index => $case) {
            $customer = $this->makeCustomer($fixture['org'], 'CUST-B'.str_pad((string) $index, 3, '0', STR_PAD_LEFT), 'B'.($index + 1));
            $customers[$index] = $customer->id;
            $this->createPostedInvoice($fixture['org'], $customer, [
                'due_date' => $asOf->copy()->subDays($case['days']),
                'balance_due' => 100,
            ]);
        }

        $props = $this->responseProps(
            $this->actingAs($fixture['user'])
                ->get('/reports/ar-aging?as_of=2026-03-01')
                ->assertOk()
        );

        foreach ($cases as $index => $case) {
            $row = collect($props['data']['rows'])->firstWhere('customer.id', $customers[$index]);
            $this->assertNotNull($row, "Missing row for case {$case['bucket']}");
            $this->assertSame(100.0, $row[$case['bucket']]);
            $this->assertSame(100.0, $row['total']);
        }
    }

    public function test_only_posted_invoices_with_a_balance_are_aged(): void
    {
        $fixture = $this->seedAging();
        $asOf = Carbon::create(2026, 2, 10);

        $this->createPostedInvoice($fixture['org'], $fixture['customer'], [
            'due_date' => $asOf,
            'balance_due' => 100,
        ]);

        CustomerInvoice::factory()->create([
            'organization_id' => $fixture['org']->id,
            'customer_id' => $fixture['customer']->id,
            'status' => CustomerInvoiceStatus::PartiallyPaid,
            'invoice_date' => $asOf->copy()->subDays(20)->toDateString(),
            'due_date' => $asOf->copy()->subDays(5)->toDateString(),
            'total_amount' => 100,
            'paid_amount' => 50,
            'balance_due' => 50,
        ]);

        $excluded = [
            ['status' => CustomerInvoiceStatus::Paid, 'balance_due' => 0, 'due_days' => 10],
            ['status' => CustomerInvoiceStatus::Voided, 'balance_due' => 100, 'due_days' => 10],
            ['status' => CustomerInvoiceStatus::Draft, 'balance_due' => 100, 'due_days' => 10],
            ['status' => CustomerInvoiceStatus::Posted, 'balance_due' => 0, 'due_days' => 10],
        ];

        foreach ($excluded as $case) {
            CustomerInvoice::factory()->create([
                'organization_id' => $fixture['org']->id,
                'customer_id' => $fixture['customer']->id,
                'status' => $case['status'],
                'invoice_date' => $asOf->copy()->subDays($case['due_days'] + 15)->toDateString(),
                'due_date' => $asOf->copy()->subDays($case['due_days'])->toDateString(),
                'total_amount' => $case['balance_due'] ?: 100,
                'paid_amount' => 0,
                'balance_due' => $case['balance_due'],
            ]);
        }

        $props = $this->responseProps(
            $this->actingAs($fixture['user'])
                ->get('/reports/ar-aging?as_of=2026-02-10')
                ->assertOk()
        );

        $this->assertCount(1, $props['data']['rows']);
        $this->assertSame(100.0, $props['data']['rows'][0]['current']);
        $this->assertSame(50.0, $props['data']['rows'][0]['days_1_30']);
        $this->assertSame(150.0, $props['data']['rows'][0]['total']);
        $this->assertSame(150.0, $props['data']['totals']['total']);
    }

    public function test_amounts_are_converted_to_base_currency(): void
    {
        $fixture = $this->seedAging();
        $asOf = Carbon::create(2026, 4, 1);

        CustomerInvoice::factory()->posted()->create([
            'organization_id' => $fixture['org']->id,
            'customer_id' => $fixture['customer']->id,
            'invoice_date' => $asOf->copy()->subDays(13)->toDateString(),
            'due_date' => $asOf->copy()->addDays(2)->toDateString(),
            'exchange_rate' => 2,
            'subtotal' => 100,
            'total_amount' => 100,
            'paid_amount' => 0,
            'balance_due' => 100,
        ]);

        $this->createPostedInvoice($fixture['org'], $fixture['customer'], [
            'due_date' => $asOf->copy()->addDays(2),
            'balance_due' => 100,
        ]);

        $props = $this->responseProps(
            $this->actingAs($fixture['user'])
                ->get('/reports/ar-aging?as_of=2026-04-01')
                ->assertOk()
        );

        $this->assertCount(1, $props['data']['rows']);
        $this->assertSame(300.0, $props['data']['rows'][0]['current']);
        $this->assertSame(300.0, $props['data']['totals']['current']);
    }

    public function test_as_of_defaults_to_today(): void
    {
        $fixture = $this->seedAging();

        $this->createPostedInvoice($fixture['org'], $fixture['customer'], [
            'due_date' => now()->copy()->subDays(5),
            'balance_due' => 100,
        ]);

        $props = $this->responseProps(
            $this->actingAs($fixture['user'])
                ->get('/reports/ar-aging')
                ->assertOk()
        );

        $this->assertSame(now()->toDateString(), $props['data']['as_of']);
        $this->assertSame(100.0, $props['data']['rows'][0]['days_1_30']);
    }

    public function test_groups_by_customer_with_global_totals(): void
    {
        $fixture = $this->seedAging();
        $asOf = Carbon::create(2026, 5, 1);

        $this->makeCustomer($fixture['org'], 'CUST-000002', 'Beta Ltd');
        $this->makeCustomer($fixture['org'], 'CUST-000003', 'Gamma Inc');

        $this->createPostedInvoice($fixture['org'], $fixture['customer'], [
            'due_date' => $asOf->copy()->addDays(1),
            'balance_due' => 100,
        ]);

        $this->createPostedInvoice($fixture['org'], Customer::where('customer_number', 'CUST-000002')->first(), [
            'due_date' => $asOf->copy()->subDays(20),
            'balance_due' => 200,
        ]);

        $this->createPostedInvoice($fixture['org'], Customer::where('customer_number', 'CUST-000003')->first(), [
            'due_date' => $asOf->copy()->subDays(70),
            'balance_due' => 300,
        ]);

        $props = $this->responseProps(
            $this->actingAs($fixture['user'])
                ->get('/reports/ar-aging?as_of=2026-05-01')
                ->assertOk()
        );

        $this->assertCount(3, $props['data']['rows']);
        $this->assertSame(100.0, collect($props['data']['rows'])->firstWhere('customer.number', 'CUST-000001')['total']);
        $this->assertSame(200.0, collect($props['data']['rows'])->firstWhere('customer.number', 'CUST-000002')['total']);
        $this->assertSame(300.0, collect($props['data']['rows'])->firstWhere('customer.number', 'CUST-000003')['total']);
        $this->assertSame(100.0, $props['data']['totals']['current']);
        $this->assertSame(200.0, $props['data']['totals']['days_1_30']);
        $this->assertSame(300.0, $props['data']['totals']['days_61_90']);
        $this->assertSame(600.0, $props['data']['totals']['total']);
    }

    public function test_is_scoped_to_the_active_organization(): void
    {
        $fixture = $this->seedAging();
        $asOf = Carbon::create(2026, 6, 1);

        $this->createPostedInvoice($fixture['org'], $fixture['customer'], [
            'due_date' => $asOf->copy()->addDays(1),
            'balance_due' => 100,
        ]);

        $foreignOrg = Organization::factory()->create();
        $foreignCustomer = $this->makeCustomer($foreignOrg, 'CUST-9001', 'Foreign Corp');
        $this->createPostedInvoice($foreignOrg, $foreignCustomer, [
            'due_date' => $asOf->copy()->addDays(1),
            'balance_due' => 900,
        ]);

        $props = $this->responseProps(
            $this->actingAs($fixture['user'])
                ->get('/reports/ar-aging?as_of=2026-06-01')
                ->assertOk()
        );

        $this->assertCount(1, $props['data']['rows']);
        $this->assertSame(100.0, $props['data']['totals']['total']);
    }

    public function test_csv_export_flattening_includes_aging_columns(): void
    {
        $fixture = $this->seedAging();
        $asOf = Carbon::create(2026, 7, 1);

        $this->createPostedInvoice($fixture['org'], $fixture['customer'], [
            'due_date' => $asOf->copy()->subDays(10),
            'balance_due' => 100,
        ]);

        $data = ReportFactory::make(ReportType::ArAging, $fixture['org'], ['as_of' => '2026-07-01'])->build();
        $csv = (new ReportCsvExporter)->export(ReportType::ArAging, $data);

        $this->assertSame(['Customer #', 'Customer Name', 'Current', '1-30 Days', '31-60 Days', '61-90 Days', '90+ Days', 'Total'], $csv['headers']);
        $this->assertCount(2, $csv['rows']);
        $this->assertSame('CUST-000001', $csv['rows'][0][0]);
        $this->assertSame(100.0, $csv['rows'][0][3]);
        $this->assertSame('Totals', $csv['rows'][1][1]);
        $this->assertSame(100.0, $csv['rows'][1][7]);
    }

    public function test_export_queues_the_ar_aging_job(): void
    {
        Queue::fake();
        $fixture = $this->seedAging();

        $this->createPostedInvoice($fixture['org'], $fixture['customer'], [
            'due_date' => now()->copy()->subDays(5),
            'balance_due' => 100,
        ]);

        $this->actingAs($fixture['user']);
        $this->confirmPassword();

        $this->post('/reports/export', [
            'report_type' => 'ar-aging',
            'as_of' => now()->toDateString(),
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(ExportReportJob::class);

        $this->assertDatabaseHas('report_exports', [
            'organization_id' => $fixture['org']->id,
            'user_id' => $fixture['user']->id,
            'report_type' => 'ar-aging',
            'status' => 'queued',
        ]);
    }
}
