<?php

namespace Tests\Feature;

use App\Actions\JournalEntry\CreateJournalEntry;
use App\Actions\JournalEntry\PostJournalEntry;
use App\Dashboard\DashboardMetricsService;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AccountType;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{org: Organization, user: User}
     */
    private function seedDashboardData(): array
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);

        $assetType = AccountType::factory()->create(['code' => 'asset', 'name' => 'Asset', 'category' => 'asset', 'normal_balance' => 'debit']);
        $liabilityType = AccountType::factory()->create(['code' => 'liability', 'name' => 'Liability', 'category' => 'liability', 'normal_balance' => 'credit']);
        $revenueType = AccountType::factory()->create(['code' => 'revenue', 'name' => 'Revenue', 'category' => 'revenue', 'normal_balance' => 'credit']);
        $expenseType = AccountType::factory()->create(['code' => 'expense', 'name' => 'Expense', 'category' => 'expense', 'normal_balance' => 'debit']);

        $cash = Account::factory()->create([
            'organization_id' => $org->id,
            'account_type_id' => $assetType->id,
            'code' => '1010',
            'name' => 'Cash on Hand',
            'normal_balance' => 'debit',
            'is_cash_account' => true,
        ]);
        $bank = Account::factory()->create([
            'organization_id' => $org->id,
            'account_type_id' => $assetType->id,
            'code' => '1020',
            'name' => 'Bank Account',
            'normal_balance' => 'debit',
            'is_cash_account' => true,
        ]);
        $revenue = Account::factory()->create([
            'organization_id' => $org->id,
            'account_type_id' => $revenueType->id,
            'code' => '4000',
            'name' => 'Sales Revenue',
            'normal_balance' => 'credit',
        ]);
        $expense = Account::factory()->create([
            'organization_id' => $org->id,
            'account_type_id' => $expenseType->id,
            'code' => '5000',
            'name' => 'Rent Expense',
            'normal_balance' => 'debit',
        ]);

        $fiscalYear = FiscalYear::factory()->create([
            'organization_id' => $org->id,
            'name' => 'FY 2026',
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
        $currencyId = $org->base_currency_id;

        $this->postEntry($org, $user, $journal, $fiscalYear, $period, $currencyId, [
            ['account_id' => $cash->id, 'debit' => 1250, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 1250],
        ], 'Sale revenue');

        $this->postEntry($org, $user, $journal, $fiscalYear, $period, $currencyId, [
            ['account_id' => $expense->id, 'debit' => 200, 'credit' => 0],
            ['account_id' => $bank->id, 'debit' => 0, 'credit' => 200],
        ], 'Rent expense');

        $receivableAccount = Account::factory()->create([
            'organization_id' => $org->id,
            'account_type_id' => $assetType->id,
            'code' => '1100',
            'name' => 'Accounts Receivable',
            'normal_balance' => 'debit',
        ]);
        $payableAccount = Account::factory()->create([
            'organization_id' => $org->id,
            'account_type_id' => $liabilityType->id,
            'code' => '2000',
            'name' => 'Accounts Payable',
            'normal_balance' => 'credit',
        ]);

        $this->seedInvoice($org, $user, 400, now()->subDay(), $receivableAccount->id);
        $this->seedBill($org, $user, 300, now()->addDays(5), $payableAccount->id);

        return ['org' => $org, 'user' => $user];
    }

    /**
     * @param  array<int, array{account_id: int, debit: int, credit: int}>  $lines
     */
    private function postEntry(
        Organization $org,
        User $user,
        Journal $journal,
        FiscalYear $fiscalYear,
        AccountingPeriod $period,
        int $currencyId,
        array $lines,
        string $description
    ): void {
        $entry = (new CreateJournalEntry)->handle([
            'journal_id' => $journal->id,
            'currency_id' => $currencyId,
            'fiscal_year_id' => $fiscalYear->id,
            'accounting_period_id' => $period->id,
            'entry_date' => now()->toDateString(),
            'description' => $description,
            'lines' => $lines,
        ], $org, $user);

        $entry->update(['status' => 'approved']);
        (new PostJournalEntry)->handle($entry, $user);
    }

    private function seedInvoice(Organization $org, User $user, float $balanceDue, string $dueDate, int $receivableAccountId): void
    {
        $customerId = DB::table('customers')->insertGetId([
            'ulid' => Str::ulid(),
            'organization_id' => $org->id,
            'customer_number' => 'CUST-001',
            'name' => 'Acme Corp',
            'email' => 'acme@example.com',
            'payment_terms_days' => 30,
            'receivable_account_id' => $receivableAccountId,
            'status' => 'active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        DB::table('customer_invoices')->insert([
            'ulid' => Str::ulid(),
            'organization_id' => $org->id,
            'customer_id' => $customerId,
            'invoice_number' => 'INV-001',
            'invoice_date' => now()->subDays(10)->toDateString(),
            'due_date' => $dueDate,
            'currency_id' => $org->base_currency_id,
            'exchange_rate' => 1,
            'base_currency_id' => $org->base_currency_id,
            'subtotal' => $balanceDue,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $balanceDue,
            'paid_amount' => 0,
            'balance_due' => $balanceDue,
            'status' => 'posted',
            'issued_at' => now(),
            'posted_at' => now(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    private function seedBill(Organization $org, User $user, float $balanceDue, string $dueDate, int $payableAccountId): void
    {
        $vendorId = DB::table('vendors')->insertGetId([
            'ulid' => Str::ulid(),
            'organization_id' => $org->id,
            'vendor_number' => 'VEND-001',
            'name' => 'Globex Suppliers',
            'email' => 'globex@example.com',
            'payment_terms_days' => 30,
            'payable_account_id' => $payableAccountId,
            'status' => 'active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        DB::table('vendor_bills')->insert([
            'ulid' => Str::ulid(),
            'organization_id' => $org->id,
            'vendor_id' => $vendorId,
            'bill_number' => 'BILL-001',
            'bill_date' => now()->subDays(5)->toDateString(),
            'due_date' => $dueDate,
            'currency_id' => $org->base_currency_id,
            'exchange_rate' => 1,
            'base_currency_id' => $org->base_currency_id,
            'subtotal' => $balanceDue,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $balanceDue,
            'paid_amount' => 0,
            'balance_due' => $balanceDue,
            'status' => 'posted',
            'posted_at' => now(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
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

    public function test_dashboard_reports_kpi_metrics_for_the_organization(): void
    {
        $seeded = $this->seedDashboardData();

        $props = $this->responseProps(
            $this->actingAs($seeded['user'])
                ->get('/dashboard')
                ->assertOk()
        );

        $metrics = $props['metrics'];

        $this->assertSame(1250.0, $metrics['cash_balance']);
        $this->assertSame(-200.0, $metrics['bank_balance']);
        $this->assertSame(400.0, $metrics['accounts_receivable']);
        $this->assertSame(300.0, $metrics['accounts_payable']);
        $this->assertSame(1, $metrics['outstanding_invoices']);
        $this->assertSame(1, $metrics['outstanding_bills']);
        $this->assertSame(1, $metrics['invoices_overdue']);
        $this->assertSame(0, $metrics['bills_overdue']);
        $this->assertSame(1250.0, $metrics['revenue']);
        $this->assertSame(200.0, $metrics['expenses']);
        $this->assertSame(1050.0, $metrics['net_income']);
        $this->assertSame('FY 2026', $metrics['measurement_period']);
        $this->assertCount(2, $metrics['recent_entries']);
    }

    public function test_posting_an_entry_invalidates_the_dashboard_metrics_cache(): void
    {
        $seeded = $this->seedDashboardData();
        $service = app(DashboardMetricsService::class);

        $metrics = $service->forOrganization($seeded['org']);
        $this->assertSame(1250.0, $metrics['revenue']);

        $key = "dashboard:{$seeded['org']->id}:metrics";
        $this->assertNotNull(Cache::get($key));

        $journal = $seeded['org']->journals()->first();
        $fiscalYear = $seeded['org']->fiscalYears()->first();
        $period = $seeded['org']->accountingPeriods()->first();
        $cash = Account::where('organization_id', $seeded['org']->id)->where('code', '1010')->first();
        $revenue = Account::where('organization_id', $seeded['org']->id)->where('code', '4000')->first();

        $entry = (new CreateJournalEntry)->handle([
            'journal_id' => $journal->id,
            'currency_id' => $seeded['org']->base_currency_id,
            'fiscal_year_id' => $fiscalYear->id,
            'accounting_period_id' => $period->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Extra sale',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 100, 'credit' => 0, 'description' => null],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 100, 'description' => null],
            ],
        ], $seeded['org'], $seeded['user']);

        $entry->update(['status' => 'approved']);
        (new PostJournalEntry)->handle($entry, $seeded['user']);

        $this->assertNull(Cache::get($key));

        $refreshed = $service->forOrganization($seeded['org']);
        $this->assertSame(1350.0, $refreshed['revenue']);
    }

    public function test_dashboard_metrics_are_scoped_to_the_organization(): void
    {
        $seeded = $this->seedDashboardData();
        $service = app(DashboardMetricsService::class);

        $foreignOrg = Organization::factory()->create();

        $metrics = $service->forOrganization($foreignOrg);

        $this->assertSame(0.0, $metrics['cash_balance']);
        $this->assertSame(0.0, $metrics['revenue']);
        $this->assertSame(0, $metrics['outstanding_invoices']);
    }
}
