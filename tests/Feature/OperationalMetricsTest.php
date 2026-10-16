<?php

namespace Tests\Feature;

use App\Actions\BankTransaction\ImportBankTransactions;
use App\Actions\CustomerPayment\AllocateCustomerPayment;
use App\Actions\JournalEntry\CreateJournalEntry;
use App\Actions\JournalEntry\PostJournalEntry;
use App\Actions\PeriodClosing\ClosePeriod;
use App\Enums\AccountingPeriodStatus;
use App\Enums\CustomerPaymentStatus;
use App\Enums\ReportExportStatus;
use App\Enums\ReportType;
use App\Exceptions\ReportExportFailedException;
use App\Jobs\ExportReportJob;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Organization;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\OperationalMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use RuntimeException;
use Tests\TestCase;

class OperationalMetricsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectMetrics(TestHandler $handler, string $metric): array
    {
        return collect($handler->getRecords())
            ->filter(fn (LogRecord $record): bool => ($record->context['metric'] ?? null) === $metric)
            ->map(fn (LogRecord $record): array => $record->context)
            ->values()
            ->all();
    }

    private function pushTestHandler(): TestHandler
    {
        $handler = new TestHandler;
        Log::getLogger()->pushHandler($handler);

        return $handler;
    }

    public function test_journal_post_success_emits_a_counter_and_latency(): void
    {
        $handler = $this->pushTestHandler();

        $org = Organization::factory()->create();
        $entry = $this->createEntry($org, User::factory()->create());
        $entry->update(['status' => 'approved']);

        (new PostJournalEntry)->handle($entry, User::factory()->create());

        $success = $this->collectMetrics($handler, 'journal_post_success_total');
        $this->assertCount(1, $success);
        $this->assertSame(1, $success[0]['value']);
        $this->assertSame($org->id, $success[0]['tags']['organization_id']);

        $latency = $this->collectMetrics($handler, 'journal_post_latency');
        $this->assertCount(1, $latency);
        $this->assertSame('milliseconds', $latency[0]['unit']);
    }

    public function test_journal_post_failure_emits_a_counter_and_latency(): void
    {
        $handler = $this->pushTestHandler();

        $org = Organization::factory()->create();
        $creator = User::factory()->create();
        $entry = $this->createEntry($org, $creator);
        $entry->update(['status' => 'approved']);

        try {
            (new PostJournalEntry)->handle($entry, $creator);
            $this->fail('The maker-checker guard was expected to reject the posting.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Maker-checker', $e->getMessage());
        }

        $failure = $this->collectMetrics($handler, 'journal_post_failure_total');
        $this->assertCount(1, $failure);
        $this->assertSame(1, $failure[0]['value']);
        $this->assertSame('RuntimeException', $failure[0]['tags']['reason']);
        $this->assertCount(1, $this->collectMetrics($handler, 'journal_post_latency'));
        $this->assertCount(0, $this->collectMetrics($handler, 'journal_post_success_total'));
    }

    public function test_period_close_emits_a_duration(): void
    {
        $handler = $this->pushTestHandler();

        $org = Organization::factory()->create();
        $fiscalYear = FiscalYear::factory()->create(['organization_id' => $org->id]);
        $period = AccountingPeriod::factory()->create([
            'organization_id' => $org->id,
            'fiscal_year_id' => $fiscalYear->id,
            'status' => AccountingPeriodStatus::Open,
        ]);

        (new ClosePeriod)->handle($period, User::factory()->create());

        $timings = $this->collectMetrics($handler, 'period_close_duration');
        $this->assertCount(1, $timings);
        $this->assertSame('milliseconds', $timings[0]['unit']);
        $this->assertSame($org->id, $timings[0]['tags']['organization_id']);
    }

    public function test_allocation_conflict_emits_a_counter(): void
    {
        $handler = $this->pushTestHandler();

        [$org, $customer, $currencyId] = $this->allocationSetup();
        $creator = User::factory()->create();
        $payment = $this->draftPayment($creator, $org, $customer, $currencyId, 100);
        $invoice = $this->postedInvoice($creator, $org, $customer, 60);

        try {
            (new AllocateCustomerPayment)->handle(
                $payment,
                [['invoice_id' => $invoice->id, 'amount' => 120]],
                User::factory()->create(),
            );
            $this->fail('The over-allocation guard was expected to reject the allocation.');
        } catch (RuntimeException) {
            // Expected.
        }

        $conflicts = $this->collectMetrics($handler, 'payment_allocation_conflict_total');
        $this->assertCount(1, $conflicts);
        $this->assertSame('payment', $conflicts[0]['tags']['resource']);
    }

    public function test_bank_import_duplicates_emit_a_counter(): void
    {
        $handler = $this->pushTestHandler();

        $org = Organization::factory()->create();
        $ledger = Account::factory()->create(['organization_id' => $org->id]);
        $bankAccount = BankAccount::factory()->create([
            'organization_id' => $org->id,
            'account_id' => $ledger->id,
            'created_by' => User::factory()->create()->id,
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'statement.csv',
            "transaction_date,debit,credit,reference,description,external_transaction_id\n"
            ."2026-01-05,100.00,,REF-001,Opening deposit,EXT-001\n"
            ."2026-01-05,100.00,,REF-001,Opening deposit,EXT-001\n"
        );

        (new ImportBankTransactions)->handle(
            ['bank_account_id' => $bankAccount->id, 'file' => $file],
            $org,
            User::factory()->create(),
        );

        $duplicates = $this->collectMetrics($handler, 'bank_import_duplicate_total');
        $this->assertCount(1, $duplicates);
        $this->assertSame(1, $duplicates[0]['value']);
        $this->assertSame($org->id, $duplicates[0]['tags']['organization_id']);
    }

    public function test_report_generation_emits_a_duration(): void
    {
        $handler = $this->pushTestHandler();
        Storage::fake('s3');

        $org = Organization::factory()->create();
        $export = ReportExport::create([
            'organization_id' => $org->id,
            'user_id' => User::factory()->create()->id,
            'report_type' => ReportType::TrialBalance,
            'filters' => [],
            'request_hash' => 'test-hash',
            'status' => ReportExportStatus::Queued,
        ]);

        try {
            (new ExportReportJob($export->id))->handle();
        } catch (ReportExportFailedException) {
            // An empty report fails permanently; generation is still measured.
        }

        $timings = $this->collectMetrics($handler, 'report_generation_duration');
        $this->assertCount(1, $timings);
        $this->assertSame('milliseconds', $timings[0]['unit']);
        $this->assertSame('export', $timings[0]['tags']['source']);
        $this->assertSame(ReportType::TrialBalance->value, $timings[0]['tags']['report_type']);
    }

    public function test_measure_returns_the_callback_result_and_records_a_timing(): void
    {
        $handler = $this->pushTestHandler();

        $result = app(OperationalMetrics::class)->measure('unit_test_duration', fn (): string => 'ok');

        $this->assertSame('ok', $result);
        $this->assertCount(1, $this->collectMetrics($handler, 'unit_test_duration'));
    }

    private function createEntry(Organization $org, User $creator): JournalEntry
    {
        $journal = Journal::factory()->create(['organization_id' => $org->id]);
        $currency = Currency::factory()->create();
        $fiscalYear = FiscalYear::factory()->create(['organization_id' => $org->id]);
        $period = AccountingPeriod::factory()->create([
            'organization_id' => $org->id,
            'fiscal_year_id' => $fiscalYear->id,
        ]);

        $cash = Account::factory()->create(['organization_id' => $org->id]);
        $revenue = Account::factory()->create(['organization_id' => $org->id]);

        return (new CreateJournalEntry)->handle([
            'journal_id' => $journal->id,
            'currency_id' => $currency->id,
            'fiscal_year_id' => $fiscalYear->id,
            'accounting_period_id' => $period->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Test entry',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 500, 'credit' => 0, 'description' => null],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 500, 'description' => null],
            ],
        ], $org, $creator);
    }

    /**
     * @return array{0: Organization, 1: Customer, 2: int}
     */
    private function allocationSetup(): array
    {
        $org = Organization::factory()->create();
        $receivable = Account::factory()->create(['organization_id' => $org->id]);
        Account::factory()->create(['organization_id' => $org->id]);
        $customer = Customer::factory()->create([
            'organization_id' => $org->id,
            'receivable_account_id' => $receivable->id,
        ]);

        return [$org, $customer, $org->base_currency_id];
    }

    private function draftPayment(
        User $creator,
        Organization $org,
        Customer $customer,
        int $currencyId,
        float $amount = 100,
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
        ]);
    }

    private function postedInvoice(
        User $creator,
        Organization $org,
        Customer $customer,
        float $balance,
    ): CustomerInvoice {
        return CustomerInvoice::factory()->posted()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'currency_id' => $org->base_currency_id,
            'base_currency_id' => $org->base_currency_id,
            'balance_due' => $balance,
        ]);
    }
}
