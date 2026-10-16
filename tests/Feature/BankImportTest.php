<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankImportBatch;
use App\Models\BankTransaction;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BankImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = "transaction_date,debit,credit,reference,description,external_transaction_id\n";

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

    private function bankSetup(): array
    {
        $org = Organization::factory()->create();
        $ledger = Account::factory()->create(['organization_id' => $org->id]);
        $bankAccount = BankAccount::factory()->create([
            'organization_id' => $org->id,
            'account_id' => $ledger->id,
            'created_by' => User::factory()->create()->id,
        ]);

        return [$org, $bankAccount];
    }

    private function csv(array $rows = []): UploadedFile
    {
        $contents = self::HEADER.implode("\n", $rows);

        return UploadedFile::fake()->createWithContent('statement.csv', $contents);
    }

    private function baseRows(): array
    {
        return [
            '2026-01-05,100.00,,REF-001,Opening deposit,EXT-001',
            '2026-01-06,25.50,,REF-002,Coffee at vendor,EXT-002',
            '2026-01-07,,500.00,REF-003,Client payment,EXT-003',
        ];
    }

    public function test_store_requires_authentication(): void
    {
        $this->post('/bank-imports', ['bank_account_id' => 1, 'file' => $this->csv()])
            ->assertRedirect(route('login'));
    }

    public function test_store_forbids_user_without_permission(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);

        $response = $this->actingAs($user)->post('/bank-imports', [
            'bank_account_id' => $bankAccount->id,
            'file' => $this->csv($this->baseRows()),
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('bank_import_batches', 0);
        $this->assertDatabaseCount('bank_transactions', 0);
    }

    public function test_store_rejects_bank_account_from_another_organization(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $otherOrg = Organization::factory()->create();
        $otherLedger = Account::factory()->create(['organization_id' => $otherOrg->id]);
        $otherAccount = BankAccount::factory()->create([
            'organization_id' => $otherOrg->id,
            'account_id' => $otherLedger->id,
            'created_by' => User::factory()->create()->id,
        ]);
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'import-bank-transactions');

        $this->actingAs($user)->post('/bank-imports', [
            'bank_account_id' => $otherAccount->id,
            'file' => $this->csv($this->baseRows()),
        ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('bank_transactions', 0);
    }

    public function test_store_rejects_inactive_bank_account(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $bankAccount->update(['status' => 'closed']);
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'import-bank-transactions');

        $this->actingAs($user)->post('/bank-imports', [
            'bank_account_id' => $bankAccount->id,
            'file' => $this->csv($this->baseRows()),
        ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('bank_transactions', 0);
    }

    public function test_store_imports_rows_with_expected_defaults(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'import-bank-transactions');

        $file = $this->csv($this->baseRows());
        $checksum = hash('sha256', (string) $file->get());

        $this->actingAs($user)->post('/bank-imports', [
            'bank_account_id' => $bankAccount->id,
            'file' => $file,
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $batch = BankImportBatch::firstOrFail();
        $this->assertSame($org->id, $batch->organization_id);
        $this->assertSame($bankAccount->id, $batch->bank_account_id);
        $this->assertSame('csv', $batch->source);
        $this->assertSame('completed', $batch->status);
        $this->assertSame($checksum, $batch->file_checksum);
        $this->assertSame(3, $batch->rows_read);
        $this->assertSame(3, $batch->rows_imported);
        $this->assertSame(0, $batch->rows_skipped);
        $this->assertSame($user->id, $batch->created_by);

        $transactions = BankTransaction::orderBy('id')->get();
        $this->assertCount(3, $transactions);

        $first = $transactions[0];
        $this->assertSame($org->id, $first->organization_id);
        $this->assertSame($bankAccount->id, $first->bank_account_id);
        $this->assertSame($batch->id, $first->import_batch_id);
        $this->assertSame('100.0000', $first->debit);
        $this->assertSame('0.0000', $first->credit);
        $this->assertSame('import', $first->source);
        $this->assertSame('unreconciled', $first->reconciliation_status);
        $this->assertSame($bankAccount->currency_id, $first->currency_id);
        $this->assertSame('EXT-001', $first->external_transaction_id);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first->transaction_hash);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'bank-import.completed',
            'auditable_type' => BankImportBatch::class,
            'auditable_id' => $batch->id,
        ]);

        // §0.29: the batch is the audit unit for an import, not the row. The
        // per-row record is deliberately NOT written, because it opened a
        // cross-connection transaction per CSV row and could commit audit
        // entries for rows a later failure rolled back. Line 165 proves the
        // row is still attributable to the batch.
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'bank-transaction.created',
            'auditable_type' => BankTransaction::class,
        ]);
    }

    public function test_store_skips_rows_already_imported_for_account(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'import-bank-transactions');

        $this->actingAs($user)->post('/bank-imports', [
            'bank_account_id' => $bankAccount->id,
            'file' => $this->csv($this->baseRows()),
        ])->assertRedirect();

        $secondFile = $this->csv(array_merge($this->baseRows(), [
            '2026-01-08,12.00,,REF-004,Second round,EXT-004',
            '2026-01-09,3.50,,REF-005,Second round again,EXT-005',
        ]));

        $this->actingAs($user)->post('/bank-imports', [
            'bank_account_id' => $bankAccount->id,
            'file' => $secondFile,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseCount('bank_transactions', 5);

        $batch = BankImportBatch::orderByDesc('id')->first();
        $this->assertSame(5, $batch->rows_read);
        $this->assertSame(2, $batch->rows_imported);
        $this->assertSame(3, $batch->rows_skipped);
    }

    public function test_retry_of_same_file_returns_original_batch(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'import-bank-transactions');

        $this->actingAs($user)->post('/bank-imports', [
            'bank_account_id' => $bankAccount->id,
            'file' => $this->csv($this->baseRows()),
        ])->assertRedirect();

        $this->actingAs($user)->post('/bank-imports', [
            'bank_account_id' => $bankAccount->id,
            'file' => $this->csv($this->baseRows()),
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseCount('bank_import_batches', 1);
        $this->assertDatabaseCount('bank_transactions', 3);
    }

    public function test_invalid_rows_fail_batch_without_persisting_then_retry_succeeds(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'import-bank-transactions');

        $invalidFile = $this->csv([
            'not-a-date,100.00,,REF-001,Opening deposit,EXT-001',
            '2026-01-06,-25.50,,REF-002,Coffee at vendor,EXT-002',
            '2026-01-07,,500.00,REF-003,Client payment,EXT-003',
        ]);

        $this->actingAs($user)->post('/bank-imports', [
            'bank_account_id' => $bankAccount->id,
            'file' => $invalidFile,
        ])->assertRedirect()->assertSessionHas('error');

        $failed = BankImportBatch::firstOrFail();
        $this->assertSame('failed', $failed->status);
        $this->assertSame(3, $failed->rows_read);
        $this->assertSame(2, $failed->rows_failed);
        $this->assertNotNull($failed->error_summary);
        $this->assertDatabaseCount('bank_transactions', 0);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'bank-import.failed',
            'auditable_type' => BankImportBatch::class,
            'auditable_id' => $failed->id,
        ]);

        $this->actingAs($user)->post('/bank-imports', [
            'bank_account_id' => $bankAccount->id,
            'file' => $this->csv($this->baseRows()),
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseCount('bank_import_batches', 2);
        $this->assertDatabaseCount('bank_transactions', 3);
        $this->assertSame(1, BankImportBatch::where('status', 'completed')->count());
    }

    public function test_within_file_duplicates_are_skipped(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'import-bank-transactions');

        $file = $this->csv([
            '2026-01-05,100.00,,REF-001,Opening deposit,EXT-001',
            '2026-01-05,100.00,,REF-001,Opening deposit,EXT-001',
        ]);

        $this->actingAs($user)->post('/bank-imports', [
            'bank_account_id' => $bankAccount->id,
            'file' => $file,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseCount('bank_transactions', 1);

        $batch = BankImportBatch::firstOrFail();
        $this->assertSame(2, $batch->rows_read);
        $this->assertSame(1, $batch->rows_imported);
        $this->assertSame(1, $batch->rows_skipped);
    }

    public function test_store_is_rate_limited_per_user(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'import-bank-transactions');

        // A statement import parses a whole file and runs one long
        // transaction, so it is the endpoint most worth bounding.
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->actingAs($user)->post('/bank-imports', [
                'bank_account_id' => $bankAccount->id,
                'file' => $this->csv(["2026-01-0{$attempt},10.00,,REF-{$attempt},Round {$attempt},EXT-{$attempt}"]),
            ])->assertRedirect();
        }

        $this->actingAs($user)->post('/bank-imports', [
            'bank_account_id' => $bankAccount->id,
            'file' => $this->csv(['2026-01-09,10.00,,REF-9,Round 9,EXT-9']),
        ])->assertStatus(429);

        $this->assertDatabaseCount('bank_import_batches', 6);
    }

    public function test_transaction_hash_is_unique_per_organization_and_account(): void
    {
        [$org, $bankAccount] = $this->bankSetup();

        BankTransaction::create([
            'organization_id' => $org->id,
            'bank_account_id' => $bankAccount->id,
            'transaction_date' => '2026-01-05',
            'debit' => 100.0,
            'credit' => 0.0,
            'currency_id' => $bankAccount->currency_id,
            'source' => 'import',
            'reconciliation_status' => 'unreconciled',
            'transaction_hash' => hash('sha256', 'first'),
        ]);

        $this->expectException(QueryException::class);

        BankTransaction::create([
            'organization_id' => $org->id,
            'bank_account_id' => $bankAccount->id,
            'transaction_date' => '2026-01-05',
            'debit' => 100.0,
            'credit' => 0.0,
            'currency_id' => $bankAccount->currency_id,
            'source' => 'import',
            'reconciliation_status' => 'unreconciled',
            'transaction_hash' => hash('sha256', 'first'),
        ]);
    }

    public function test_transaction_hash_is_rerouted_across_organizations(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        [$otherOrg, $otherAccount] = $this->bankSetup();

        $payload = [
            'transaction_date' => '2026-01-05',
            'debit' => 100.0,
            'credit' => 0.0,
            'currency_id' => $bankAccount->currency_id,
            'source' => 'import',
            'reconciliation_status' => 'unreconciled',
            'transaction_hash' => hash('sha256', 'shared'),
        ];

        BankTransaction::create(array_merge($payload, [
            'organization_id' => $org->id,
            'bank_account_id' => $bankAccount->id,
            'currency_id' => $bankAccount->currency_id,
        ]));

        $transaction = BankTransaction::create(array_merge($payload, [
            'organization_id' => $otherOrg->id,
            'bank_account_id' => $otherAccount->id,
            'currency_id' => $otherAccount->currency_id,
        ]));

        $this->assertSame($otherAccount->id, $transaction->bank_account_id);
    }

    public function test_completed_batch_checksum_is_unique_per_organization_and_account(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $creator = User::factory()->create();

        BankImportBatch::create([
            'organization_id' => $org->id,
            'bank_account_id' => $bankAccount->id,
            'source' => 'csv',
            'file_name' => 'a.csv',
            'file_checksum' => hash('sha256', 'same'),
            'started_at' => now(),
            'completed_at' => now(),
            'status' => 'completed',
            'created_by' => $creator->id,
        ]);

        $this->expectException(QueryException::class);

        BankImportBatch::create([
            'organization_id' => $org->id,
            'bank_account_id' => $bankAccount->id,
            'source' => 'csv',
            'file_name' => 'b.csv',
            'file_checksum' => hash('sha256', 'same'),
            'started_at' => now(),
            'completed_at' => now(),
            'status' => 'completed',
            'created_by' => $creator->id,
        ]);
    }

    public function test_failed_batch_does_not_block_checksum_reuse(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $creator = User::factory()->create();
        $checksum = hash('sha256', 'retry-me');

        BankImportBatch::create([
            'organization_id' => $org->id,
            'bank_account_id' => $bankAccount->id,
            'source' => 'csv',
            'file_name' => 'a.csv',
            'file_checksum' => $checksum,
            'started_at' => now(),
            'completed_at' => now(),
            'status' => 'failed',
            'created_by' => $creator->id,
        ]);

        $completed = BankImportBatch::create([
            'organization_id' => $org->id,
            'bank_account_id' => $bankAccount->id,
            'source' => 'csv',
            'file_name' => 'b.csv',
            'file_checksum' => $checksum,
            'started_at' => now(),
            'completed_at' => now(),
            'status' => 'completed',
            'created_by' => $creator->id,
        ]);

        $this->assertSame('completed', $completed->fresh()->status);
    }

    public function test_index_lists_only_current_organization_import_batches(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        [$otherOrg, $otherAccount] = $this->bankSetup();
        $creator = User::factory()->create();
        $otherCreator = User::factory()->create();

        [$first, $second] = [$bankAccount->id, $bankAccount->id];
        BankImportBatch::create([
            'organization_id' => $org->id,
            'bank_account_id' => $first,
            'source' => 'csv',
            'file_name' => 'one.csv',
            'status' => 'completed',
            'created_by' => $creator->id,
        ]);
        BankImportBatch::create([
            'organization_id' => $org->id,
            'bank_account_id' => $second,
            'source' => 'csv',
            'file_name' => 'two.csv',
            'status' => 'completed',
            'created_by' => $creator->id,
        ]);
        BankImportBatch::create([
            'organization_id' => $otherOrg->id,
            'bank_account_id' => $otherAccount->id,
            'source' => 'csv',
            'file_name' => 'other.csv',
            'status' => 'completed',
            'created_by' => $otherCreator->id,
        ]);

        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, ['view-bank-transactions', 'import-bank-transactions']);

        $this->actingAs($user)->get('/bank-transactions')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('BankTransactions/Index')
                ->has('importBatches', 2)
                ->where('importBatches.0.file_name', 'two.csv')
                ->where('importBatches.1.file_name', 'one.csv')
                ->where('canImport', true));
    }

    public function test_index_reports_cannot_import_without_permission(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'view-bank-transactions');

        $this->actingAs($user)->get('/bank-transactions')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('BankTransactions/Index')
                ->where('canImport', false));
    }
}
