<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BankTransactionTest extends TestCase
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

    private function storePayload(int $bankAccountId): array
    {
        return [
            'bank_account_id' => $bankAccountId,
            'transaction_date' => '2026-01-15',
            'value_date' => '2026-01-16',
            'direction' => 'debit',
            'amount' => 250.75,
            'reference' => 'DEP-001',
            'description' => 'Cash deposit',
        ];
    }

    public function test_index_requires_authentication(): void
    {
        $this->get('/bank-transactions')->assertRedirect(route('login'));
    }

    public function test_index_forbids_user_without_permission(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);

        $response = $this->actingAs($user)->get('/bank-transactions');

        $response->assertStatus(403);
    }

    public function test_index_allows_user_with_permission(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'view-bank-transactions');

        $this->actingAs($user)->get('/bank-transactions')->assertOk();
    }

    public function test_store_forbids_user_without_permission(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);

        $response = $this->actingAs($user)->post('/bank-transactions', $this->storePayload($bankAccount->id));

        $response->assertForbidden();
        $this->assertDatabaseMissing('bank_transactions', ['reference' => 'DEP-001']);
    }

    public function test_store_creates_debit_transaction_with_audit(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-bank-transactions');

        $this->actingAs($user)->post('/bank-transactions', $this->storePayload($bankAccount->id))->assertRedirect();

        $transaction = BankTransaction::where('reference', 'DEP-001')->firstOrFail();

        $this->assertSame($org->id, $transaction->organization_id);
        $this->assertSame($bankAccount->id, $transaction->bank_account_id);
        $this->assertSame('250.7500', $transaction->debit);
        $this->assertSame('0.0000', $transaction->credit);
        $this->assertSame('manual', $transaction->source);
        $this->assertSame('unreconciled', $transaction->reconciliation_status);
        $this->assertSame($bankAccount->currency_id, $transaction->currency_id);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'bank-transaction.created',
            'auditable_type' => BankTransaction::class,
            'auditable_id' => $transaction->id,
        ]);
    }

    public function test_store_creates_credit_transaction(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-bank-transactions');

        $this->actingAs($user)->post('/bank-transactions', [
            ...$this->storePayload($bankAccount->id),
            'direction' => 'credit',
            'amount' => 99.99,
        ])->assertRedirect();

        $transaction = BankTransaction::where('transaction_date', '2026-01-15')->firstOrFail();

        $this->assertSame('0.0000', $transaction->debit);
        $this->assertSame('99.9900', $transaction->credit);
    }

    public function test_store_requires_fields(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-bank-transactions');

        $this->actingAs($user)->post('/bank-transactions', [])
            ->assertRedirect()
            ->assertSessionHasErrors(['bank_account_id', 'transaction_date', 'direction', 'amount']);
    }

    public function test_store_rejects_zero_amount(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-bank-transactions');

        $this->actingAs($user)->post('/bank-transactions', [
            ...$this->storePayload($bankAccount->id),
            'amount' => 0,
        ])->assertRedirect()->assertSessionHasErrors('amount');
    }

    public function test_store_rejects_invalid_direction(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-bank-transactions');

        $this->actingAs($user)->post('/bank-transactions', [
            ...$this->storePayload($bankAccount->id),
            'direction' => 'transfer',
        ])->assertRedirect()->assertSessionHasErrors('direction');
    }

    public function test_store_rejects_cross_organization_bank_account(): void
    {
        [$org] = $this->bankSetup();
        [$foreignOrg, $foreignBankAccount] = $this->bankSetup();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-bank-transactions');

        $this->actingAs($user)->post('/bank-transactions', $this->storePayload($foreignBankAccount->id))
            ->assertRedirect()
            ->assertSessionHas('error', 'The bank account must belong to the same organization.');

        $this->assertDatabaseMissing('bank_transactions', ['reference' => 'DEP-001']);
    }

    public function test_store_rejects_inactive_bank_account(): void
    {
        [$org] = $this->bankSetup();
        $ledger = Account::factory()->create(['organization_id' => $org->id]);
        $inactiveBank = BankAccount::factory()->create([
            'organization_id' => $org->id,
            'account_id' => $ledger->id,
            'status' => 'inactive',
            'created_by' => User::factory()->create()->id,
        ]);
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-bank-transactions');

        $this->actingAs($user)->post('/bank-transactions', $this->storePayload($inactiveBank->id))
            ->assertRedirect()
            ->assertSessionHas('error', 'The bank account must be active.');

        $this->assertDatabaseMissing('bank_transactions', ['reference' => 'DEP-001']);
    }

    public function test_index_isolates_transactions_by_tenant(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        BankTransaction::factory()->debit(100)->create([
            'organization_id' => $org->id,
            'bank_account_id' => $bankAccount->id,
            'currency_id' => $bankAccount->currency_id,
            'description' => 'My Transaction',
        ]);

        [$foreignOrg, $foreignBank] = $this->bankSetup();
        BankTransaction::factory()->credit(50)->create([
            'organization_id' => $foreignOrg->id,
            'bank_account_id' => $foreignBank->id,
            'currency_id' => $foreignBank->currency_id,
            'description' => 'Foreign Transaction',
        ]);

        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'view-bank-transactions');

        $response = $this->actingAs($user)->get('/bank-transactions?bank_account_id='.$bankAccount->id);

        $response->assertOk();
        $response->assertSee('My Transaction');
        $response->assertDontSee('Foreign Transaction');
    }

    public function test_update_forbids_user_without_permission(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $transaction = BankTransaction::factory()->debit(100)->create([
            'organization_id' => $org->id,
            'bank_account_id' => $bankAccount->id,
            'currency_id' => $bankAccount->currency_id,
        ]);
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);

        $response = $this->actingAs($user)->put("/bank-transactions/{$transaction->id}", $this->storePayload($bankAccount->id));

        $response->assertForbidden();
    }

    public function test_update_modifies_transaction_and_audits(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $transaction = BankTransaction::factory()->debit(100)->create([
            'organization_id' => $org->id,
            'bank_account_id' => $bankAccount->id,
            'currency_id' => $bankAccount->currency_id,
        ]);
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'update-bank-transactions');

        $this->actingAs($user)->put("/bank-transactions/{$transaction->id}", [
            ...$this->storePayload($bankAccount->id),
            'amount' => 500,
            'direction' => 'credit',
            'description' => 'Updated description',
        ])->assertRedirect();

        $fresh = $transaction->fresh();

        $this->assertSame('0.0000', $fresh->debit);
        $this->assertSame('500.0000', $fresh->credit);
        $this->assertSame('Updated description', $fresh->description);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'bank-transaction.updated',
            'auditable_type' => BankTransaction::class,
            'auditable_id' => $transaction->id,
        ]);
    }

    public function test_update_rejects_cross_organization_transaction_with_404(): void
    {
        [$org] = $this->bankSetup();
        [$foreignOrg, $foreignBank] = $this->bankSetup();
        $foreignTransaction = BankTransaction::factory()->debit(100)->create([
            'organization_id' => $foreignOrg->id,
            'bank_account_id' => $foreignBank->id,
            'currency_id' => $foreignBank->currency_id,
        ]);
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'update-bank-transactions');

        $this->actingAs($user)->put("/bank-transactions/{$foreignTransaction->id}", $this->storePayload($foreignBank->id))
            ->assertStatus(404);
    }

    public function test_update_rejects_bank_account_from_another_organization(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        [$foreignOrg, $foreignBank] = $this->bankSetup();
        $transaction = BankTransaction::factory()->debit(100)->create([
            'organization_id' => $org->id,
            'bank_account_id' => $bankAccount->id,
            'currency_id' => $bankAccount->currency_id,
        ]);
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'update-bank-transactions');

        // The transaction is the caller's, but the target bank account is not:
        // without this guard the update would re-point it across the tenant line.
        $this->actingAs($user)->put("/bank-transactions/{$transaction->id}", $this->storePayload($foreignBank->id))
            ->assertRedirect()
            ->assertSessionHas('error', 'The bank account must belong to the same organization.');

        $this->assertSame($bankAccount->id, $transaction->fresh()->bank_account_id);
    }

    public function test_update_rejects_inactive_bank_account(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $closedAccount = BankAccount::factory()->create([
            'organization_id' => $org->id,
            'account_id' => Account::factory()->create(['organization_id' => $org->id])->id,
            'status' => 'closed',
            'created_by' => User::factory()->create()->id,
        ]);
        $transaction = BankTransaction::factory()->debit(100)->create([
            'organization_id' => $org->id,
            'bank_account_id' => $bankAccount->id,
            'currency_id' => $bankAccount->currency_id,
        ]);
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'update-bank-transactions');

        $this->actingAs($user)->put("/bank-transactions/{$transaction->id}", $this->storePayload($closedAccount->id))
            ->assertRedirect()
            ->assertSessionHas('error', 'The bank account must be active.');

        $this->assertSame($bankAccount->id, $transaction->fresh()->bank_account_id);
    }

    public function test_update_rejects_imported_transaction(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $transaction = BankTransaction::factory()->imported()->debit(100)->create([
            'organization_id' => $org->id,
            'bank_account_id' => $bankAccount->id,
            'currency_id' => $bankAccount->currency_id,
        ]);
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'update-bank-transactions');

        $this->actingAs($user)->put("/bank-transactions/{$transaction->id}", $this->storePayload($bankAccount->id))
            ->assertRedirect()
            ->assertSessionHas('error', 'Only unreconciled manual bank transactions can be edited.');

        $this->assertSame('100.0000', $transaction->fresh()->debit);
    }

    public function test_update_rejects_reconciled_transaction(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $transaction = BankTransaction::factory()->reconciled()->debit(100)->create([
            'organization_id' => $org->id,
            'bank_account_id' => $bankAccount->id,
            'currency_id' => $bankAccount->currency_id,
        ]);
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'update-bank-transactions');

        $this->actingAs($user)->put("/bank-transactions/{$transaction->id}", $this->storePayload($bankAccount->id))
            ->assertRedirect()
            ->assertSessionHas('error', 'Only unreconciled manual bank transactions can be edited.');

        $this->assertSame('100.0000', $transaction->fresh()->debit);
    }

    public function test_database_rejects_transaction_with_both_debit_and_credit(): void
    {
        [$org, $bankAccount] = $this->bankSetup();

        try {
            DB::table('bank_transactions')->insert($this->rawRow($bankAccount, ['debit' => 100, 'credit' => 100]));
        } catch (QueryException $e) {
            $this->assertSame('23514', $e->errorInfo[0]);

            return;
        }

        $this->fail('Expected a CHECK constraint violation for both debit and credit.');
    }

    public function test_database_rejects_transaction_with_zero_amount(): void
    {
        [$org, $bankAccount] = $this->bankSetup();

        try {
            DB::table('bank_transactions')->insert($this->rawRow($bankAccount, ['debit' => 0, 'credit' => 0]));
        } catch (QueryException $e) {
            $this->assertSame('23514', $e->errorInfo[0]);

            return;
        }

        $this->fail('Expected a CHECK constraint violation for a zero-amount transaction.');
    }

    public function test_audit_chain_verification_remains_valid_after_transaction_create(): void
    {
        [$org, $bankAccount] = $this->bankSetup();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-bank-transactions');

        $this->actingAs($user)->post('/bank-transactions', $this->storePayload($bankAccount->id))->assertRedirect();

        $result = app(AuditLogService::class)->verify($org);

        $this->assertTrue($result->valid);
        $this->assertSame(1, $result->checkedCount);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function rawRow(BankAccount $bankAccount, array $overrides = []): array
    {
        return array_merge([
            'organization_id' => $bankAccount->organization_id,
            'bank_account_id' => $bankAccount->id,
            'transaction_date' => '2026-01-15',
            'debit' => 100,
            'credit' => 0,
            'currency_id' => $bankAccount->currency_id,
            'source' => 'manual',
            'reconciliation_status' => 'unreconciled',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }
}
