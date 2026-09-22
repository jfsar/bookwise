<?php

namespace Tests\Feature;

use App\Enums\ReportType;
use App\Enums\VendorBillStatus;
use App\Enums\VendorDebitNoteStatus;
use App\Jobs\ExportReportJob;
use App\Models\Account;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorDebitNote;
use App\Reports\ReportCsvExporter;
use App\Reports\ReportFactory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ApAgingReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seeds an organization with an active user, the view-reports permission
     * and a vendor, and returns the collectively created records.
     *
     * @return array{org: Organization, user: User, vendor: Vendor}
     */
    private function seedAging(): array
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);
        session(['current_organization_id' => $org->id]);
        $this->grantPermission($user, $org, 'view-reports');

        $vendor = $this->makeVendor($org, 'VEND-000001', 'Widgets Inc');

        return ['org' => $org, 'user' => $user, 'vendor' => $vendor];
    }

    private function makeVendor(Organization $org, string $number, string $name): Vendor
    {
        $payable = Account::factory()->create(['organization_id' => $org->id]);

        return Vendor::factory()->create([
            'organization_id' => $org->id,
            'vendor_number' => $number,
            'name' => $name,
            'payable_account_id' => $payable->id,
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
     * @param  array{due_date: Carbon, balance_due: int|float}  $bill
     */
    private function createPostedBill(Organization $org, Vendor $vendor, array $bill): VendorBill
    {
        return VendorBill::factory()->posted()->create([
            'organization_id' => $org->id,
            'vendor_id' => $vendor->id,
            'bill_date' => $bill['due_date']->copy()->subDays(15)->toDateString(),
            'due_date' => $bill['due_date']->toDateString(),
            'total_amount' => $bill['balance_due'],
            'paid_amount' => 0,
            'balance_due' => $bill['balance_due'],
        ]);
    }

    private function seedPostedDebitNote(Organization $org, VendorBill $bill, float $total): VendorDebitNote
    {
        return VendorDebitNote::factory()->posted()->create([
            'organization_id' => $org->id,
            'vendor_id' => $bill->vendor_id,
            'bill_id' => $bill->id,
            'currency_id' => $org->base_currency_id,
            'exchange_rate' => 1,
            'subtotal' => $total,
            'tax_amount' => 0,
            'total_amount' => $total,
            'status' => VendorDebitNoteStatus::Posted,
            'created_by' => User::factory()->create()->id,
        ]);
    }

    public function test_page_forbids_users_without_permission(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);
        session(['current_organization_id' => $org->id]);

        $this->actingAs($user)
            ->get('/reports/ap-aging')
            ->assertForbidden();
    }

    public function test_aging_buckets_bills_by_due_date(): void
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
            $this->createPostedBill($fixture['org'], $fixture['vendor'], [
                'due_date' => $asOf->copy()->subDays($case['days']),
                'balance_due' => $case['balance_due'],
            ]);
        }

        $props = $this->responseProps(
            $this->actingAs($fixture['user'])
                ->get('/reports/ap-aging?as_of=2026-01-15')
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

        $vendors = [];

        foreach ($cases as $index => $case) {
            $vendor = $this->makeVendor($fixture['org'], 'VEND-B'.str_pad((string) $index, 3, '0', STR_PAD_LEFT), 'B'.($index + 1));
            $vendors[$index] = $vendor->id;
            $this->createPostedBill($fixture['org'], $vendor, [
                'due_date' => $asOf->copy()->subDays($case['days']),
                'balance_due' => 100,
            ]);
        }

        $props = $this->responseProps(
            $this->actingAs($fixture['user'])
                ->get('/reports/ap-aging?as_of=2026-03-01')
                ->assertOk()
        );

        foreach ($cases as $index => $case) {
            $row = collect($props['data']['rows'])->firstWhere('vendor.id', $vendors[$index]);
            $this->assertNotNull($row, "Missing row for case {$case['bucket']}");
            $this->assertSame(100.0, $row[$case['bucket']]);
            $this->assertSame(100.0, $row['total']);
        }
    }

    public function test_only_posted_bills_with_a_balance_are_aged(): void
    {
        $fixture = $this->seedAging();
        $asOf = Carbon::create(2026, 2, 10);

        $this->createPostedBill($fixture['org'], $fixture['vendor'], [
            'due_date' => $asOf,
            'balance_due' => 100,
        ]);

        VendorBill::factory()->create([
            'organization_id' => $fixture['org']->id,
            'vendor_id' => $fixture['vendor']->id,
            'status' => VendorBillStatus::PartiallyPaid,
            'bill_date' => $asOf->copy()->subDays(20)->toDateString(),
            'due_date' => $asOf->copy()->subDays(5)->toDateString(),
            'total_amount' => 100,
            'paid_amount' => 50,
            'balance_due' => 50,
        ]);

        $excluded = [
            ['status' => VendorBillStatus::Paid, 'balance_due' => 0, 'due_days' => 10],
            ['status' => VendorBillStatus::Voided, 'balance_due' => 100, 'due_days' => 10],
            ['status' => VendorBillStatus::Draft, 'balance_due' => 100, 'due_days' => 10],
            ['status' => VendorBillStatus::Posted, 'balance_due' => 0, 'due_days' => 10],
        ];

        foreach ($excluded as $case) {
            VendorBill::factory()->create([
                'organization_id' => $fixture['org']->id,
                'vendor_id' => $fixture['vendor']->id,
                'status' => $case['status'],
                'bill_date' => $asOf->copy()->subDays($case['due_days'] + 15)->toDateString(),
                'due_date' => $asOf->copy()->subDays($case['due_days'])->toDateString(),
                'total_amount' => $case['balance_due'] ?: 100,
                'paid_amount' => 0,
                'balance_due' => $case['balance_due'],
            ]);
        }

        $props = $this->responseProps(
            $this->actingAs($fixture['user'])
                ->get('/reports/ap-aging?as_of=2026-02-10')
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

        VendorBill::factory()->posted()->create([
            'organization_id' => $fixture['org']->id,
            'vendor_id' => $fixture['vendor']->id,
            'bill_date' => $asOf->copy()->subDays(13)->toDateString(),
            'due_date' => $asOf->copy()->addDays(2)->toDateString(),
            'exchange_rate' => 2,
            'subtotal' => 100,
            'total_amount' => 100,
            'paid_amount' => 0,
            'balance_due' => 100,
        ]);

        $this->createPostedBill($fixture['org'], $fixture['vendor'], [
            'due_date' => $asOf->copy()->addDays(2),
            'balance_due' => 100,
        ]);

        $props = $this->responseProps(
            $this->actingAs($fixture['user'])
                ->get('/reports/ap-aging?as_of=2026-04-01')
                ->assertOk()
        );

        $this->assertCount(1, $props['data']['rows']);
        $this->assertSame(300.0, $props['data']['rows'][0]['current']);
        $this->assertSame(300.0, $props['data']['totals']['current']);
    }

    public function test_aging_nets_posted_debit_notes_from_bill_balances(): void
    {
        $fixture = $this->seedAging();
        $asOf = Carbon::create(2026, 4, 10);

        $debitedBill = $this->createPostedBill($fixture['org'], $fixture['vendor'], [
            'due_date' => $asOf->copy()->addDays(1),
            'balance_due' => 100,
        ]);

        $this->seedPostedDebitNote($fixture['org'], $debitedBill, 40);

        $this->createPostedBill($fixture['org'], $fixture['vendor'], [
            'due_date' => $asOf->copy()->addDays(1),
            'balance_due' => 100,
        ]);

        $props = $this->responseProps(
            $this->actingAs($fixture['user'])
                ->get('/reports/ap-aging?as_of=2026-04-10')
                ->assertOk()
        );

        $this->assertCount(1, $props['data']['rows']);
        $row = $props['data']['rows'][0];
        $this->assertSame(160.0, $row['current']);
        $this->assertSame(160.0, $row['total']);
        $this->assertSame(160.0, $props['data']['totals']['current']);
        $this->assertSame(160.0, $props['data']['totals']['total']);
    }

    public function test_bills_fully_covered_by_debit_notes_are_omitted_from_aging(): void
    {
        $fixture = $this->seedAging();
        $asOf = Carbon::create(2026, 4, 10);

        $coveredBill = $this->createPostedBill($fixture['org'], $fixture['vendor'], [
            'due_date' => $asOf->copy()->subDays(10),
            'balance_due' => 100,
        ]);

        $this->seedPostedDebitNote($fixture['org'], $coveredBill, 100);

        $this->createPostedBill($fixture['org'], $fixture['vendor'], [
            'due_date' => $asOf->copy()->subDays(10),
            'balance_due' => 50,
        ]);

        $props = $this->responseProps(
            $this->actingAs($fixture['user'])
                ->get('/reports/ap-aging?as_of=2026-04-10')
                ->assertOk()
        );

        $this->assertCount(1, $props['data']['rows']);
        $this->assertSame(50.0, $props['data']['rows'][0]['days_1_30']);
        $this->assertSame(50.0, $props['data']['rows'][0]['total']);
        $this->assertSame(50.0, $props['data']['totals']['days_1_30']);
    }

    public function test_as_of_defaults_to_today(): void
    {
        $fixture = $this->seedAging();

        $this->createPostedBill($fixture['org'], $fixture['vendor'], [
            'due_date' => now()->copy()->subDays(5),
            'balance_due' => 100,
        ]);

        $props = $this->responseProps(
            $this->actingAs($fixture['user'])
                ->get('/reports/ap-aging')
                ->assertOk()
        );

        $this->assertSame(now()->toDateString(), $props['data']['as_of']);
        $this->assertSame(100.0, $props['data']['rows'][0]['days_1_30']);
    }

    public function test_groups_by_vendor_with_global_totals(): void
    {
        $fixture = $this->seedAging();
        $asOf = Carbon::create(2026, 5, 1);

        $this->makeVendor($fixture['org'], 'VEND-000002', 'Beta Ltd');
        $this->makeVendor($fixture['org'], 'VEND-000003', 'Gamma Inc');

        $this->createPostedBill($fixture['org'], $fixture['vendor'], [
            'due_date' => $asOf->copy()->addDays(1),
            'balance_due' => 100,
        ]);

        $this->createPostedBill($fixture['org'], Vendor::where('vendor_number', 'VEND-000002')->first(), [
            'due_date' => $asOf->copy()->subDays(20),
            'balance_due' => 200,
        ]);

        $this->createPostedBill($fixture['org'], Vendor::where('vendor_number', 'VEND-000003')->first(), [
            'due_date' => $asOf->copy()->subDays(70),
            'balance_due' => 300,
        ]);

        $props = $this->responseProps(
            $this->actingAs($fixture['user'])
                ->get('/reports/ap-aging?as_of=2026-05-01')
                ->assertOk()
        );

        $this->assertCount(3, $props['data']['rows']);
        $this->assertSame(100.0, collect($props['data']['rows'])->firstWhere('vendor.number', 'VEND-000001')['total']);
        $this->assertSame(200.0, collect($props['data']['rows'])->firstWhere('vendor.number', 'VEND-000002')['total']);
        $this->assertSame(300.0, collect($props['data']['rows'])->firstWhere('vendor.number', 'VEND-000003')['total']);
        $this->assertSame(100.0, $props['data']['totals']['current']);
        $this->assertSame(200.0, $props['data']['totals']['days_1_30']);
        $this->assertSame(300.0, $props['data']['totals']['days_61_90']);
        $this->assertSame(600.0, $props['data']['totals']['total']);
    }

    public function test_is_scoped_to_the_active_organization(): void
    {
        $fixture = $this->seedAging();
        $asOf = Carbon::create(2026, 6, 1);

        $this->createPostedBill($fixture['org'], $fixture['vendor'], [
            'due_date' => $asOf->copy()->addDays(1),
            'balance_due' => 100,
        ]);

        $foreignOrg = Organization::factory()->create();
        $foreignVendor = $this->makeVendor($foreignOrg, 'VEND-9001', 'Foreign Corp');
        $this->createPostedBill($foreignOrg, $foreignVendor, [
            'due_date' => $asOf->copy()->addDays(1),
            'balance_due' => 900,
        ]);

        $props = $this->responseProps(
            $this->actingAs($fixture['user'])
                ->get('/reports/ap-aging?as_of=2026-06-01')
                ->assertOk()
        );

        $this->assertCount(1, $props['data']['rows']);
        $this->assertSame(100.0, $props['data']['totals']['total']);
    }

    public function test_csv_export_flattening_includes_aging_columns(): void
    {
        $fixture = $this->seedAging();
        $asOf = Carbon::create(2026, 7, 1);

        $this->createPostedBill($fixture['org'], $fixture['vendor'], [
            'due_date' => $asOf->copy()->subDays(10),
            'balance_due' => 100,
        ]);

        $data = ReportFactory::make(ReportType::ApAging, $fixture['org'], ['as_of' => '2026-07-01'])->build();
        $csv = (new ReportCsvExporter)->export(ReportType::ApAging, $data);

        $this->assertSame(['Vendor #', 'Vendor Name', 'Current', '1-30 Days', '31-60 Days', '61-90 Days', '90+ Days', 'Total'], $csv['headers']);
        $this->assertCount(2, $csv['rows']);
        $this->assertSame('VEND-000001', $csv['rows'][0][0]);
        $this->assertSame(100.0, $csv['rows'][0][3]);
        $this->assertSame('Totals', $csv['rows'][1][1]);
        $this->assertSame(100.0, $csv['rows'][1][7]);
    }

    public function test_export_queues_the_ap_aging_job(): void
    {
        Queue::fake();
        $fixture = $this->seedAging();

        $this->createPostedBill($fixture['org'], $fixture['vendor'], [
            'due_date' => now()->copy()->subDays(5),
            'balance_due' => 100,
        ]);

        $this->actingAs($fixture['user']);
        $this->confirmPassword();

        $this->post('/reports/export', [
            'report_type' => 'ap-aging',
            'as_of' => now()->toDateString(),
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(ExportReportJob::class);

        $this->assertDatabaseHas('report_exports', [
            'organization_id' => $fixture['org']->id,
            'user_id' => $fixture['user']->id,
            'report_type' => 'ap-aging',
            'status' => 'queued',
        ]);
    }
}
