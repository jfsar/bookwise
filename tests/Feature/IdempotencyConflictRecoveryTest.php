<?php

namespace Tests\Feature;

use App\Actions\CustomerInvoice\CreateCustomerInvoice;
use App\Models\Account;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Exercises the recovery path taken when a concurrent request commits a row
 * with the same idempotency key between this request's pre-check and its
 * INSERT (the unique index then rejects the INSERT with SQLSTATE 23505).
 *
 * A committed, concurrently-inserted "winner" row is required, which means the
 * fixtures must be visible on a second connection. DatabaseTruncation is used
 * (instead of RefreshDatabase's wrapping transaction) so every fixture is
 * committed and a simulated second connection can see it.
 */
class IdempotencyConflictRecoveryTest extends TestCase
{
    use DatabaseTruncation;

    /**
     * This test commits fixtures (they must be visible on a second connection),
     * so the tables are truncated again on the way out to keep the shared test
     * database clean for the RefreshDatabase tests that follow.
     */
    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    public function test_create_invoice_recovers_when_a_concurrent_request_wins_the_race(): void
    {
        $creator = User::factory()->create();
        $organization = Organization::factory()->create();
        $revenue = Account::factory()->create(['organization_id' => $organization->id]);
        $receivable = Account::factory()->create(['organization_id' => $organization->id]);
        $customer = Customer::factory()->create([
            'organization_id' => $organization->id,
            'receivable_account_id' => $receivable->id,
        ]);

        $key = 'race-'.Str::ulid();

        config(['database.connections.simulator' => config('database.connections.pgsql_testing')]);
        DB::purge('simulator');

        CustomerInvoice::creating(function (CustomerInvoice $invoice) use ($organization, $customer, $creator, $key): void {
            if ($invoice->idempotency_key !== $key) {
                return;
            }

            DB::connection('simulator')->table('customer_invoices')->insert([
                'ulid' => (string) Str::ulid(),
                'organization_id' => $organization->id,
                'customer_id' => $customer->id,
                'invoice_number' => 'INV-RACE-000001',
                'invoice_date' => today()->toDateString(),
                'due_date' => today()->addDays(15)->toDateString(),
                'currency_id' => $organization->base_currency_id,
                'base_currency_id' => $organization->base_currency_id,
                'created_by' => $creator->id,
                'idempotency_key' => $key,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $invoice = app(CreateCustomerInvoice::class)->handle([
            'customer_id' => $customer->id,
            'invoice_date' => today()->toDateString(),
            'due_date' => today()->addDays(15)->toDateString(),
            'currency_id' => $organization->base_currency_id,
            'exchange_rate' => 1,
            'idempotency_key' => $key,
            'lines' => [
                [
                    'description' => 'Consulting services',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'revenue_account_id' => $revenue->id,
                ],
            ],
        ], $organization, $creator);

        $this->assertSame($key, $invoice->idempotency_key);
        $this->assertSame('INV-RACE-000001', $invoice->invoice_number);
        $this->assertSame(1, CustomerInvoice::where('idempotency_key', $key)->count());
    }
}
