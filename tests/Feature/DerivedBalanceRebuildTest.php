<?php

namespace Tests\Feature;

use App\Actions\JournalEntry\CreateJournalEntry;
use App\Actions\JournalEntry\PostJournalEntry;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AccountType;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalEntryLine;
use App\Models\Organization;
use App\Models\User;
use App\Services\DerivedBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Derived reporting structures are rebuildable from authoritative posted
 * journal data (§0.17, §0.23, INV-012, acceptance criterion #47).
 */
class DerivedBalanceRebuildTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seeds an organization with two posted entries on consecutive days.
     *
     * @return array{organization: Organization, cash: Account, revenue: Account, day1: string, day2: string}
     */
    private function seedLedger(): array
    {
        $organization = Organization::factory()->create();
        $creator = User::factory()->create();
        $poster = User::factory()->create();

        $assetType = AccountType::firstOrCreate(
            ['code' => 'asset'],
            ['name' => 'Asset', 'category' => 'asset', 'normal_balance' => 'debit', 'sort_order' => 1],
        );
        $revenueType = AccountType::firstOrCreate(
            ['code' => 'revenue'],
            ['name' => 'Revenue', 'category' => 'revenue', 'normal_balance' => 'credit', 'sort_order' => 2],
        );

        $cash = Account::factory()->create([
            'organization_id' => $organization->id,
            'account_type_id' => $assetType->id,
            'code' => '1010',
            'name' => 'Cash on Hand',
            'normal_balance' => 'debit',
        ]);
        $revenue = Account::factory()->create([
            'organization_id' => $organization->id,
            'account_type_id' => $revenueType->id,
            'code' => '4000',
            'name' => 'Sales Revenue',
            'normal_balance' => 'credit',
        ]);

        $fiscalYear = FiscalYear::factory()->create([
            'organization_id' => $organization->id,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);
        $period = AccountingPeriod::factory()->create([
            'organization_id' => $organization->id,
            'fiscal_year_id' => $fiscalYear->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);
        $journal = Journal::factory()->create(['organization_id' => $organization->id]);

        $day1 = now()->startOfMonth()->toDateString();
        $day2 = now()->startOfMonth()->addDay()->toDateString();

        foreach ([[$day1, 1000.0], [$day2, 500.0]] as [$date, $amount]) {
            $entry = (new CreateJournalEntry)->handle([
                'journal_id' => $journal->id,
                'currency_id' => $organization->base_currency_id,
                'fiscal_year_id' => $fiscalYear->id,
                'accounting_period_id' => $period->id,
                'entry_date' => $date,
                'description' => 'Seed revenue',
                'lines' => [
                    ['account_id' => $cash->id, 'debit' => $amount, 'credit' => 0, 'description' => null],
                    ['account_id' => $revenue->id, 'debit' => 0, 'credit' => $amount, 'description' => null],
                ],
            ], $organization, $creator);

            $entry->update(['status' => 'approved']);
            (new PostJournalEntry)->handle($entry, $poster);
        }

        return [
            'organization' => $organization,
            'cash' => $cash,
            'revenue' => $revenue,
            'day1' => $day1,
            'day2' => $day2,
        ];
    }

    public function test_rebuild_populates_derived_structures_from_posted_lines(): void
    {
        $context = $this->seedLedger();

        $counts = app(DerivedBalanceService::class)->rebuild($context['organization']);

        $this->assertSame(4, $counts['facts']);
        $this->assertSame(4, $counts['daily']);
        $this->assertSame(4, $counts['snapshots']);
        $this->assertDatabaseCount('reporting_fact_tables', 4);
        $this->assertDatabaseCount('account_daily_balances', 4);
        $this->assertDatabaseCount('ledger_balance_snapshots', 4);

        $cashDay2 = DB::table('account_daily_balances')
            ->where('account_id', $context['cash']->id)
            ->where('balance_date', $context['day2'])
            ->first();

        $this->assertSame('500.0000', $cashDay2->debit_total);
        $this->assertSame('0.0000', $cashDay2->credit_total);
        $this->assertSame('500.0000', $cashDay2->net_change);
        $this->assertSame('1500.0000', $cashDay2->closing_balance);

        $revenueDay2 = DB::table('account_daily_balances')
            ->where('account_id', $context['revenue']->id)
            ->where('balance_date', $context['day2'])
            ->first();

        $this->assertSame('500.0000', $revenueDay2->credit_total);
        $this->assertSame('-500.0000', $revenueDay2->net_change);
        $this->assertSame('-1500.0000', $revenueDay2->closing_balance);

        $cashSnapshot = DB::table('ledger_balance_snapshots')
            ->where('account_id', $context['cash']->id)
            ->where('as_of_date', $context['day2'])
            ->first();

        $this->assertSame('1500.0000', $cashSnapshot->debit_balance);
        $this->assertSame('0.0000', $cashSnapshot->credit_balance);
        $this->assertSame('1500.0000', $cashSnapshot->net_balance);

        $debitFact = DB::table('reporting_fact_tables')
            ->where('account_id', $context['cash']->id)
            ->where('debit_amount', '>', 0)
            ->orderBy('entry_date')
            ->first();

        $this->assertSame('1000.0000', $debitFact->debit_amount);
        $this->assertSame('0.0000', $debitFact->credit_amount);
        $this->assertSame('1000.0000', $debitFact->base_amount);
    }

    public function test_rebuild_is_deterministic_and_idempotent(): void
    {
        $context = $this->seedLedger();
        $service = app(DerivedBalanceService::class);

        $service->rebuild($context['organization']);
        $first = $this->derivedDump();

        $service->rebuild($context['organization']);
        $second = $this->derivedDump();

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('reporting_fact_tables', 4);
    }

    public function test_unposted_entries_are_excluded_from_derived_structures(): void
    {
        $context = $this->seedLedger();

        $fiscalYear = FiscalYear::where('organization_id', $context['organization']->id)->firstOrFail();
        $period = AccountingPeriod::where('organization_id', $context['organization']->id)->firstOrFail();
        $journal = Journal::where('organization_id', $context['organization']->id)->firstOrFail();

        $creator = User::factory()->create();
        (new CreateJournalEntry)->handle([
            'journal_id' => $journal->id,
            'currency_id' => $context['organization']->base_currency_id,
            'fiscal_year_id' => $fiscalYear->id,
            'accounting_period_id' => $period->id,
            'entry_date' => $context['day2'],
            'description' => 'Draft only',
            'lines' => [
                ['account_id' => $context['cash']->id, 'debit' => 75, 'credit' => 0, 'description' => null],
                ['account_id' => $context['revenue']->id, 'debit' => 0, 'credit' => 75, 'description' => null],
            ],
        ], $context['organization'], $creator);

        // Draft line is still written to the authoritative table...
        $this->assertSame(6, DB::table('journal_entry_lines')->count());

        $counts = app(DerivedBalanceService::class)->rebuild($context['organization']);

        // ...but only posted lines produce reporting facts.
        $this->assertSame(4, $counts['facts']);
        $this->assertDatabaseCount('reporting_fact_tables', 4);
    }

    public function test_verify_reports_consistency_after_rebuild(): void
    {
        $context = $this->seedLedger();
        $service = app(DerivedBalanceService::class);

        $service->rebuild($context['organization']);

        $result = $service->verify($context['organization']);

        $this->assertTrue($result['consistent']);
        $this->assertSame(4, $result['tables']['reporting_fact_tables']['expected']);
        $this->assertSame(0, $result['tables']['account_daily_balances']['differences']);
        $this->assertSame(0, $result['tables']['ledger_balance_snapshots']['differences']);
    }

    public function test_verify_detects_drift_and_rebuild_repairs_it(): void
    {
        $context = $this->seedLedger();
        $service = app(DerivedBalanceService::class);
        $service->rebuild($context['organization']);

        DB::table('account_daily_balances')
            ->where('account_id', $context['cash']->id)
            ->where('balance_date', $context['day2'])
            ->update(['closing_balance' => 999]);

        DB::table('ledger_balance_snapshots')
            ->where('account_id', $context['revenue']->id)
            ->delete();

        $drift = $service->verify($context['organization']);

        $this->assertFalse($drift['consistent']);
        $this->assertGreaterThan(0, $drift['tables']['account_daily_balances']['differences']);
        $this->assertGreaterThan(0, $drift['tables']['ledger_balance_snapshots']['differences']);

        $this->artisan('accounting:rebuild-derived-balances --check')->assertExitCode(1);
        $this->artisan('accounting:rebuild-derived-balances --organization='.$context['organization']->id)
            ->assertExitCode(0);

        $this->assertTrue($service->verify($context['organization'])['consistent']);
    }

    public function test_check_command_passes_when_structures_are_consistent(): void
    {
        $context = $this->seedLedger();
        app(DerivedBalanceService::class)->rebuild($context['organization']);

        $this->artisan('accounting:rebuild-derived-balances --check')->assertExitCode(0);
    }

    public function test_rebuild_leaves_posted_accounting_facts_untouched(): void
    {
        $context = $this->seedLedger();

        $before = DB::table('journal_entry_lines')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        app(DerivedBalanceService::class)->rebuild($context['organization']);

        $after = DB::table('journal_entry_lines')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $this->assertEquals($before, $after);
    }

    public function test_rebuild_is_organization_scoped(): void
    {
        $context = $this->seedLedger();
        $other = $this->seedLedger();

        app(DerivedBalanceService::class)->rebuild($context['organization']);

        $this->assertSame(4, DB::table('reporting_fact_tables')->where('organization_id', $context['organization']->id)->count());
        $this->assertSame(0, DB::table('reporting_fact_tables')->where('organization_id', $other['organization']->id)->count());
        $this->assertSame(0, DB::table('account_daily_balances')->where('organization_id', $other['organization']->id)->count());
        $this->assertSame(0, DB::table('ledger_balance_snapshots')->where('organization_id', $other['organization']->id)->count());
    }

    public function test_rebuild_command_rejects_unknown_organization(): void
    {
        $this->artisan('accounting:rebuild-derived-balances --organization=999999')
            ->assertExitCode(1);
    }

    public function test_derived_amounts_use_base_currency_for_foreign_entries(): void
    {
        $context = $this->seedLedger();
        $organization = $context['organization'];

        $fiscalYear = FiscalYear::where('organization_id', $organization->id)->firstOrFail();
        $period = AccountingPeriod::where('organization_id', $organization->id)->firstOrFail();
        $journal = Journal::where('organization_id', $organization->id)->firstOrFail();
        $foreign = Currency::factory()->create();

        $creator = User::factory()->create();
        $poster = User::factory()->create();

        $entry = (new CreateJournalEntry)->handle([
            'journal_id' => $journal->id,
            'currency_id' => $foreign->id,
            'fiscal_year_id' => $fiscalYear->id,
            'accounting_period_id' => $period->id,
            'entry_date' => $context['day2'],
            'description' => 'Foreign currency sale',
            'exchange_rate' => 2,
            'lines' => [
                ['account_id' => $context['cash']->id, 'debit' => 100, 'credit' => 0, 'description' => null],
                ['account_id' => $context['revenue']->id, 'debit' => 0, 'credit' => 100, 'description' => null],
            ],
        ], $organization, $creator);

        $entry->update(['status' => 'approved']);
        (new PostJournalEntry)->handle($entry, $poster);

        $service = app(DerivedBalanceService::class);
        $service->rebuild($organization);

        // 100 foreign units at rate 2 report as 200 base units, not 100.
        $fact = DB::table('reporting_fact_tables')
            ->where('account_id', $context['cash']->id)
            ->where('entry_number', $entry->entry_number)
            ->first();

        $this->assertNotNull($fact);
        $this->assertSame('200.0000', $fact->debit_amount);
        $this->assertSame('0.0000', $fact->credit_amount);
        $this->assertSame('200.0000', $fact->base_amount);

        $daily = DB::table('account_daily_balances')
            ->where('account_id', $context['cash']->id)
            ->where('balance_date', $context['day2'])
            ->first();

        $this->assertSame('700.0000', $daily->debit_total);
        $this->assertSame('1700.0000', $daily->closing_balance);
    }

    public function test_verify_detects_and_rebuild_removes_extra_derived_rows(): void
    {
        $context = $this->seedLedger();
        $organization = $context['organization'];
        $service = app(DerivedBalanceService::class);
        $service->rebuild($organization);

        $extraDate = now()->startOfMonth()->addDays(10)->toDateString();

        DB::table('account_daily_balances')->insert([
            'organization_id' => $organization->id,
            'account_id' => $context['cash']->id,
            'balance_date' => $extraDate,
            'debit_total' => '1.0000',
            'credit_total' => '0.0000',
            'net_change' => '1.0000',
            'closing_balance' => '1.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ledger_balance_snapshots')->insert([
            'organization_id' => $organization->id,
            'account_id' => $context['cash']->id,
            'as_of_date' => $extraDate,
            'debit_balance' => '1.0000',
            'credit_balance' => '0.0000',
            'net_balance' => '1.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fiscalYear = FiscalYear::where('organization_id', $organization->id)->firstOrFail();
        $period = AccountingPeriod::where('organization_id', $organization->id)->firstOrFail();
        $journal = Journal::where('organization_id', $organization->id)->firstOrFail();
        $creator = User::factory()->create();

        $draft = (new CreateJournalEntry)->handle([
            'journal_id' => $journal->id,
            'currency_id' => $organization->base_currency_id,
            'fiscal_year_id' => $fiscalYear->id,
            'accounting_period_id' => $period->id,
            'entry_date' => $extraDate,
            'description' => 'Never posted',
            'lines' => [
                ['account_id' => $context['cash']->id, 'debit' => 50, 'credit' => 0, 'description' => null],
                ['account_id' => $context['revenue']->id, 'debit' => 0, 'credit' => 50, 'description' => null],
            ],
        ], $organization, $creator);

        $draftLine = JournalEntryLine::where('journal_entry_id', $draft->id)
            ->where('account_id', $context['cash']->id)
            ->firstOrFail();

        DB::table('reporting_fact_tables')->insert([
            'organization_id' => $organization->id,
            'journal_entry_id' => $draft->id,
            'journal_entry_line_id' => $draftLine->id,
            'account_id' => $draftLine->account_id,
            'journal_id' => $draft->journal_id,
            'fiscal_year_id' => $draft->fiscal_year_id,
            'accounting_period_id' => $draft->accounting_period_id,
            'entry_date' => $extraDate,
            'entry_number' => $draft->entry_number,
            'account_code' => $context['cash']->code,
            'debit_amount' => '50.0000',
            'credit_amount' => '0.0000',
            'base_amount' => '50.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $drift = $service->verify($organization);

        $this->assertFalse($drift['consistent']);
        $this->assertSame(1, $drift['tables']['reporting_fact_tables']['differences']);
        $this->assertSame(1, $drift['tables']['account_daily_balances']['differences']);
        $this->assertSame(1, $drift['tables']['ledger_balance_snapshots']['differences']);

        $service->rebuild($organization);

        $this->assertTrue($service->verify($organization)['consistent']);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function derivedDump(): array
    {
        $columns = [
            'reporting_fact_tables' => ['organization_id', 'journal_entry_id', 'journal_entry_line_id', 'account_id', 'journal_id', 'fiscal_year_id', 'accounting_period_id', 'entry_date', 'entry_number', 'account_code', 'debit_amount', 'credit_amount', 'base_amount'],
            'account_daily_balances' => ['organization_id', 'account_id', 'balance_date', 'debit_total', 'credit_total', 'net_change', 'closing_balance'],
            'ledger_balance_snapshots' => ['organization_id', 'account_id', 'as_of_date', 'debit_balance', 'credit_balance', 'net_balance'],
        ];

        $order = [
            'reporting_fact_tables' => ['journal_entry_line_id'],
            'account_daily_balances' => ['account_id', 'balance_date'],
            'ledger_balance_snapshots' => ['account_id', 'as_of_date'],
        ];

        $dump = [];

        foreach ($columns as $table => $fields) {
            $query = DB::table($table);
            foreach ($order[$table] as $column) {
                $query->orderBy($column);
            }

            $dump[$table] = $query->get($fields)
                ->map(fn ($row) => (array) $row)
                ->all();
        }

        return $dump;
    }
}
