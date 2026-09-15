<?php

namespace Tests\Feature;

use App\Actions\JournalEntry\CreateJournalEntry;
use App\Actions\JournalEntry\PostJournalEntry;
use App\Jobs\ExportReportJob;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AccountType;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\ReportExport;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seeds an organization with a posted revenue entry and returns the
     * collectively created records.
     *
     * @return array{org: Organization, user: User, entry: mixed, accounts: array<string, Account>}
     */
    private function seedLedger(): array
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);
        $this->grantPermission($user, $org, 'view-reports');

        $cashType = AccountType::factory()->create(['code' => 'asset', 'name' => 'Asset', 'category' => 'asset', 'normal_balance' => 'debit']);
        $revenueType = AccountType::factory()->create(['code' => 'revenue', 'name' => 'Revenue', 'category' => 'revenue', 'normal_balance' => 'credit']);
        $expenseType = AccountType::factory()->create(['code' => 'expense', 'name' => 'Expense', 'category' => 'expense', 'normal_balance' => 'debit']);
        $equityType = AccountType::factory()->create(['code' => 'equity', 'name' => 'Equity', 'category' => 'equity', 'normal_balance' => 'credit']);

        $accounts = [
            'cash' => Account::factory()->create([
                'organization_id' => $org->id,
                'account_type_id' => $cashType->id,
                'code' => '1010',
                'name' => 'Cash on Hand',
                'normal_balance' => 'debit',
                'is_cash_account' => true,
            ]),
            'revenue' => Account::factory()->create([
                'organization_id' => $org->id,
                'account_type_id' => $revenueType->id,
                'code' => '4000',
                'name' => 'Sales Revenue',
                'normal_balance' => 'credit',
                'cash_flow_section' => 'operating',
            ]),
            'expense' => Account::factory()->create([
                'organization_id' => $org->id,
                'account_type_id' => $expenseType->id,
                'code' => '5000',
                'name' => 'Rent Expense',
                'normal_balance' => 'debit',
                'cash_flow_section' => 'operating',
            ]),
            'equity' => Account::factory()->create([
                'organization_id' => $org->id,
                'account_type_id' => $equityType->id,
                'code' => '3000',
                'name' => "Owner's Equity",
                'normal_balance' => 'credit',
            ]),
        ];

        $fiscalYear = FiscalYear::factory()->create([
            'organization_id' => $org->id,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);
        $period = AccountingPeriod::factory()->create([
            'organization_id' => $org->id,
            'fiscal_year_id' => $fiscalYear->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);
        $journal = Journal::factory()->create(['organization_id' => $org->id]);

        $entry = (new CreateJournalEntry)->handle([
            'journal_id' => $journal->id,
            'currency_id' => $org->base_currency_id,
            'fiscal_year_id' => $fiscalYear->id,
            'accounting_period_id' => $period->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Seed revenue',
            'lines' => [
                ['account_id' => $accounts['cash']->id, 'debit' => 1250, 'credit' => 0, 'description' => null],
                ['account_id' => $accounts['revenue']->id, 'debit' => 0, 'credit' => 1250, 'description' => null],
            ],
        ], $org, $user);

        $entry->update(['status' => 'approved']);
        $poster = User::factory()->create();
        (new PostJournalEntry)->handle($entry, $poster);

        return ['org' => $org, 'user' => $user, 'entry' => $entry, 'accounts' => $accounts];
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

    public function test_report_pages_forbid_users_without_permission(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);

        $this->actingAs($user)
            ->get('/reports/general-ledger')
            ->assertForbidden();
    }

    public function test_general_ledger_lists_posted_lines_with_totals(): void
    {
        $ledger = $this->seedLedger();

        $props = $this->responseProps(
            $this->actingAs($ledger['user'])
                ->get('/reports/general-ledger')
                ->assertOk()
        );

        $this->assertCount(2, $props['data']['rows']);
        $this->assertSame(1250.0, $props['data']['totals']['debit']);
        $this->assertSame(1250.0, $props['data']['totals']['credit']);
        $this->assertSame('1010', $props['data']['rows'][0]['account']['code']);
        $this->assertSame(1250.0, $props['data']['rows'][0]['running_balance']);
    }

    public function test_trial_balance_is_balanced_and_reports_period_totals(): void
    {
        $ledger = $this->seedLedger();

        $props = $this->responseProps(
            $this->actingAs($ledger['user'])
                ->get('/reports/trial-balance')
                ->assertOk()
        );

        $this->assertTrue($props['data']['balanced']);
        $this->assertSame(1250.0, $props['data']['totals']['period_debit']);
        $this->assertSame(1250.0, $props['data']['totals']['period_credit']);
        $this->assertSame(1250.0, $props['data']['totals']['closing_debit']);
        $this->assertSame(1250.0, $props['data']['totals']['closing_credit']);
    }

    public function test_income_statement_reports_net_income_from_period_activity(): void
    {
        $ledger = $this->seedLedger();

        $props = $this->responseProps(
            $this->actingAs($ledger['user'])
                ->get('/reports/income-statement')
                ->assertOk()
        );

        $this->assertSame('Sales Revenue', $props['data']['sections']['revenue'][0]['name']);
        $this->assertSame(1250.0, $props['data']['totals']['revenue']);
        $this->assertSame(0.0, $props['data']['totals']['expenses']);
        $this->assertSame(1250.0, $props['data']['totals']['net_income']);
    }

    public function test_balance_sheet_balances_with_cumulative_retained_earnings(): void
    {
        $ledger = $this->seedLedger();

        $props = $this->responseProps(
            $this->actingAs($ledger['user'])
                ->get('/reports/balance-sheet')
                ->assertOk()
        );

        $this->assertTrue($props['data']['balanced']);
        $this->assertSame(1250.0, $props['data']['retained_earnings']);
        $this->assertSame(1250.0, $props['data']['totals']['assets']);
        $this->assertSame(1250.0, $props['data']['totals']['equity']);
        $this->assertSame(0.0, $props['data']['totals']['liabilities']);
    }

    public function test_cash_flow_classifies_operating_activity_and_reports_cash_change(): void
    {
        $ledger = $this->seedLedger();

        $props = $this->responseProps(
            $this->actingAs($ledger['user'])
                ->get('/reports/cash-flow')
                ->assertOk()
        );

        $this->assertSame(1250.0, $props['data']['totals']['operating']);
        $this->assertSame(0.0, $props['data']['totals']['investing']);
        $this->assertSame(0.0, $props['data']['totals']['financing']);
        $this->assertSame(1250.0, $props['data']['net_cash_change']);
    }

    public function test_export_queues_job_and_creates_export_record(): void
    {
        Queue::fake();
        $ledger = $this->seedLedger();

        $this->actingAs($ledger['user']);
        $this->confirmPassword();

        $this->post('/reports/export', [
            'report_type' => 'trial-balance',
            'account_id' => $ledger['accounts']['cash']->id,
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(ExportReportJob::class);

        $this->assertDatabaseHas('report_exports', [
            'organization_id' => $ledger['org']->id,
            'user_id' => $ledger['user']->id,
            'report_type' => 'trial-balance',
            'status' => 'queued',
        ]);
    }

    public function test_general_ledger_is_scoped_to_the_active_organization(): void
    {
        $ledger = $this->seedLedger();

        $foreignOrg = Organization::factory()->create();
        $foreignUser = User::factory()->create();
        $foreignUser->organizations()->attach($foreignOrg->id, ['membership_status' => 'active']);
        $this->grantPermission($foreignUser, $foreignOrg, 'view-reports');
        $this->seedRevenueEntry($foreignOrg, $foreignUser);

        $props = $this->responseProps(
            $this->actingAs($ledger['user'])
                ->get('/reports/general-ledger')
                ->assertOk()
        );

        $this->assertCount(2, $props['data']['rows']);
        $this->assertSame(1250.0, $props['data']['totals']['debit']);
    }

    public function test_download_returns_the_completed_export_file(): void
    {
        Storage::fake('local');
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);
        $this->grantPermission($user, $org, 'view-reports');

        $export = ReportExport::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'report_type' => 'trial-balance',
            'filters' => [],
            'request_hash' => str_repeat('a', 64),
            'status' => 'completed',
            'file_name' => 'trial-balance.csv',
            'file_path' => 'reports/1.csv',
            'file_size' => 5,
            'completed_at' => now(),
        ]);
        Storage::disk('local')->put('reports/1.csv', 'code,name');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->get("/reports/exports/{$export->id}")
            ->assertOk()
            ->assertDownload('trial-balance.csv');
    }

    public function test_download_forbids_users_outside_the_organization(): void
    {
        Storage::fake('local');
        $org = Organization::factory()->create();
        $owner = User::factory()->create();
        $owner->organizations()->attach($org->id, ['membership_status' => 'active']);
        $this->grantPermission($owner, $org, 'view-reports');

        $export = ReportExport::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'report_type' => 'trial-balance',
            'filters' => [],
            'request_hash' => str_repeat('b', 64),
            'status' => 'completed',
            'file_name' => 'trial-balance.csv',
            'file_path' => 'reports/2.csv',
            'file_size' => 5,
            'completed_at' => now(),
        ]);
        Storage::disk('local')->put('reports/2.csv', 'code,name');

        $intruder = User::factory()->create();
        $intruderOrg = Organization::factory()->create();
        $intruder->organizations()->attach($intruderOrg->id, ['membership_status' => 'active']);
        $this->grantPermission($intruder, $intruderOrg, 'view-reports');

        $this->actingAs($intruder);
        $this->confirmPassword();

        $this->get("/reports/exports/{$export->id}")
            ->assertForbidden();
    }

    public function test_exports_endpoint_lists_the_users_recent_exports(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);
        $this->grantPermission($user, $org, 'view-reports');

        $completed = ReportExport::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'report_type' => 'trial-balance',
            'filters' => [],
            'request_hash' => str_repeat('a', 64),
            'status' => 'completed',
            'file_name' => 'trial-balance.csv',
            'file_path' => 'reports/3.csv',
            'file_size' => 5,
            'completed_at' => now(),
        ]);

        $failed = ReportExport::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'report_type' => 'general-ledger',
            'filters' => [],
            'request_hash' => str_repeat('b', 64),
            'status' => 'failed',
            'error_message' => 'Disk full',
            'completed_at' => now(),
        ]);
        $failed->created_at = now()->subMinutes(5);
        $failed->save();

        $response = $this->actingAs($user)->getJson('/reports/exports')->assertOk();

        $recentExports = $response->json('recentExports');

        $this->assertCount(2, $recentExports);
        $this->assertSame($completed->id, $recentExports[0]['id']);
        $this->assertSame('trial-balance.csv', $recentExports[0]['file_name']);
        $this->assertNotNull($recentExports[0]['download_url']);
        $this->assertNull($recentExports[1]['download_url']);
        $this->assertArrayNotHasKey('error_message', $recentExports[0]);
        $this->assertArrayNotHasKey('error_message', $recentExports[1]);
        $this->assertArrayNotHasKey('file_path', $recentExports[0]);
        $this->assertArrayNotHasKey('file_path', $recentExports[1]);
    }

    public function test_exports_endpoint_forbids_users_without_permission(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);

        $this->actingAs($user)
            ->getJson('/reports/exports')
            ->assertForbidden();
    }

    public function test_report_page_props_do_not_expose_internal_export_fields(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);
        $this->grantPermission($user, $org, 'view-reports');

        ReportExport::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'report_type' => 'trial-balance',
            'filters' => [],
            'request_hash' => str_repeat('c', 64),
            'status' => 'completed',
            'file_name' => 'trial-balance.csv',
            'file_path' => 'reports/4.csv',
            'file_size' => 5,
            'completed_at' => now(),
        ]);

        $props = $this->responseProps(
            $this->actingAs($user)->get('/reports/general-ledger')->assertOk()
        );

        $this->assertCount(1, $props['recentExports']);
        $this->assertArrayNotHasKey('error_message', $props['recentExports'][0]);
        $this->assertArrayNotHasKey('file_path', $props['recentExports'][0]);
        $this->assertArrayHasKey('download_url', $props['recentExports'][0]);
    }

    /**
     * @return array{org: Organization, user: User}
     */
    private function seedRevenueEntry(Organization $org, User $user): array
    {
        $cashType = AccountType::firstOrCreate(['code' => 'asset'], ['name' => 'Asset', 'category' => 'asset', 'normal_balance' => 'debit']);
        $revenueType = AccountType::firstOrCreate(['code' => 'revenue'], ['name' => 'Revenue', 'category' => 'revenue', 'normal_balance' => 'credit']);

        $cash = Account::factory()->create([
            'organization_id' => $org->id,
            'account_type_id' => $cashType->id,
            'code' => '1010',
            'name' => 'Cash on Hand',
            'normal_balance' => 'debit',
        ]);
        $revenue = Account::factory()->create([
            'organization_id' => $org->id,
            'account_type_id' => $revenueType->id,
            'code' => '4000',
            'name' => 'Sales Revenue',
            'normal_balance' => 'credit',
        ]);

        $fiscalYear = FiscalYear::factory()->create([
            'organization_id' => $org->id,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);
        $period = AccountingPeriod::factory()->create([
            'organization_id' => $org->id,
            'fiscal_year_id' => $fiscalYear->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);
        $journal = Journal::factory()->create(['organization_id' => $org->id]);
        $currency = $org->baseCurrency;

        $entry = (new CreateJournalEntry)->handle([
            'journal_id' => $journal->id,
            'currency_id' => $currency->id,
            'fiscal_year_id' => $fiscalYear->id,
            'accounting_period_id' => $period->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Foreign revenue',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 999, 'credit' => 0, 'description' => null],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 999, 'description' => null],
            ],
        ], $org, $user);

        $entry->update(['status' => 'approved']);
        $poster = User::factory()->create();
        (new PostJournalEntry)->handle($entry, $poster);

        return ['org' => $org, 'user' => $user];
    }
}
