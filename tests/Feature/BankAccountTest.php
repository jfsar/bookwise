<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BankAccountTest extends TestCase
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

    private function bankOrganization(): array
    {
        $org = Organization::factory()->create();
        $ledger = Account::factory()->create(['organization_id' => $org->id]);

        return [$org, $ledger];
    }

    private function storePayload(int $ledgerAccountId, int $currencyId): array
    {
        return [
            'bank_name' => 'Acme Bank',
            'account_name' => 'Operating Account',
            'account_number' => '012345678901',
            'iban' => 'US001234567890',
            'routing_number' => '110000000',
            'account_id' => $ledgerAccountId,
            'currency_id' => $currencyId,
            'opening_balance' => 5000,
            'status' => 'active',
        ];
    }

    public function test_index_requires_authentication(): void
    {
        $this->get('/bank-accounts')->assertRedirect(route('login'));
    }

    public function test_index_forbids_user_without_permission(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);

        $response = $this->actingAs($user)->get('/bank-accounts');

        $response->assertStatus(403);
    }

    public function test_index_allows_user_with_permission(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'view-bank-accounts');

        $this->actingAs($user)->get('/bank-accounts')->assertOk();
    }

    public function test_store_forbids_user_without_permission(): void
    {
        [$org, $ledger] = $this->bankOrganization();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);

        $this->actingAs($user);
        $this->confirmPassword();

        $response = $this->post('/bank-accounts', $this->storePayload($ledger->id, $org->base_currency_id));

        $response->assertForbidden();
        $this->assertDatabaseMissing('bank_accounts', ['bank_name' => 'Acme Bank']);
    }

    public function test_store_creates_bank_account_with_protected_number_and_audit(): void
    {
        [$org, $ledger] = $this->bankOrganization();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-bank-accounts');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/bank-accounts', $this->storePayload($ledger->id, $org->base_currency_id))->assertRedirect();

        $this->assertDatabaseHas('bank_accounts', [
            'organization_id' => $org->id,
            'bank_name' => 'Acme Bank',
            'account_id' => $ledger->id,
        ]);

        $bankAccount = BankAccount::where('organization_id', $org->id)->firstOrFail();

        $this->assertSame('••••••••8901', $bankAccount->masked_account_number);
        $this->assertSame('8901', $bankAccount->last4);
        $this->assertSame('012345678901', $bankAccount->encrypted_account_number);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'bank-account.created',
            'auditable_type' => BankAccount::class,
        ]);
    }

    public function test_full_number_is_encrypted_at_rest(): void
    {
        [$org, $ledger] = $this->bankOrganization();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-bank-accounts');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/bank-accounts', $this->storePayload($ledger->id, $org->base_currency_id))->assertRedirect();

        $raw = DB::table('bank_accounts')->where('organization_id', $org->id)->value('encrypted_account_number');

        $this->assertNotSame('012345678901', $raw);
        $this->assertStringContainsString('eyJpdiI6', $raw);
    }

    public function test_audit_log_never_stores_the_full_account_number(): void
    {
        [$org, $ledger] = $this->bankOrganization();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-bank-accounts');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/bank-accounts', $this->storePayload($ledger->id, $org->base_currency_id))->assertRedirect();

        $audit = AuditLog::where('organization_id', $org->id)
            ->where('action', 'bank-account.created')
            ->firstOrFail();

        $newValues = $audit->new_values;
        $json = json_encode($newValues, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('012345678901', $json);
        $this->assertStringContainsString('••••••••8901', $json);
        $this->assertStringContainsString('8901', $json);
        $this->assertArrayNotHasKey('encrypted_account_number', $newValues);
        $this->assertNull($audit->old_values);
    }

    public function test_index_serializes_only_the_masked_number(): void
    {
        [$org, $ledger] = $this->bankOrganization();
        BankAccount::factory()->create([
            'organization_id' => $org->id,
            'account_id' => $ledger->id,
            'encrypted_account_number' => '012345678901',
            'masked_account_number' => '••••••••8901',
            'last4' => '8901',
            'created_by' => User::factory()->create()->id,
        ]);

        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'view-bank-accounts');

        $response = $this->actingAs($user)->get('/bank-accounts');

        $response
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('BankAccounts/Index')
                ->where('bankAccounts.data.0.masked_account_number', '••••••••8901')
                ->where('bankAccounts.data.0.last4', '8901')
                ->missing('bankAccounts.data.0.encrypted_account_number'));
    }

    public function test_store_requires_fields(): void
    {
        [$org, $ledger] = $this->bankOrganization();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-bank-accounts');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/bank-accounts', ['account_id' => $ledger->id])
            ->assertRedirect()
            ->assertSessionHasErrors(['bank_name', 'account_name', 'currency_id', 'opening_balance', 'status']);
    }

    public function test_store_rejects_negative_opening_balance(): void
    {
        [$org, $ledger] = $this->bankOrganization();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-bank-accounts');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/bank-accounts', [
            ...$this->storePayload($ledger->id, $org->base_currency_id),
            'opening_balance' => -1,
        ])->assertRedirect()->assertSessionHasErrors('opening_balance');
    }

    public function test_store_rejects_invalid_status(): void
    {
        [$org, $ledger] = $this->bankOrganization();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-bank-accounts');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/bank-accounts', [
            ...$this->storePayload($ledger->id, $org->base_currency_id),
            'status' => 'frozen',
        ])->assertRedirect()->assertSessionHasErrors('status');
    }

    public function test_store_rejects_cross_organization_ledger_account(): void
    {
        [$org] = $this->bankOrganization();
        $otherOrgLedger = Account::factory()->create();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-bank-accounts');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/bank-accounts', $this->storePayload($otherOrgLedger->id, $org->base_currency_id))
            ->assertRedirect()
            ->assertSessionHas('error', 'The ledger account must belong to the same organization.');

        $this->assertDatabaseMissing('bank_accounts', ['bank_name' => 'Acme Bank']);
    }

    public function test_store_rejects_non_posting_ledger_account(): void
    {
        [$org] = $this->bankOrganization();
        $header = Account::factory()->create([
            'organization_id' => $org->id,
            'is_posting_account' => false,
        ]);
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-bank-accounts');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/bank-accounts', $this->storePayload($header->id, $org->base_currency_id))
            ->assertRedirect()
            ->assertSessionHas('error', 'The ledger account must be an active posting account.');

        $this->assertDatabaseMissing('bank_accounts', ['bank_name' => 'Acme Bank']);
    }

    public function test_update_forbids_user_without_permission(): void
    {
        [$org, $ledger] = $this->bankOrganization();
        $bankAccount = BankAccount::factory()->create([
            'organization_id' => $org->id,
            'account_id' => $ledger->id,
            'created_by' => User::factory()->create()->id,
        ]);
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);

        $this->actingAs($user);
        $this->confirmPassword();

        $response = $this->put("/bank-accounts/{$bankAccount->id}", $this->storePayload($ledger->id, $org->base_currency_id));

        $response->assertForbidden();
    }

    public function test_update_modifies_bank_account_and_audits(): void
    {
        [$org, $ledger] = $this->bankOrganization();
        $bankAccount = BankAccount::factory()->create([
            'organization_id' => $org->id,
            'account_id' => $ledger->id,
            'created_by' => User::factory()->create()->id,
        ]);
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'update-bank-accounts');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->put("/bank-accounts/{$bankAccount->id}", [
            ...$this->storePayload($ledger->id, $org->base_currency_id),
            'bank_name' => 'Renamed Bank',
            'account_number' => '',
        ])->assertRedirect();

        $this->assertDatabaseHas('bank_accounts', [
            'id' => $bankAccount->id,
            'bank_name' => 'Renamed Bank',
            'updated_by' => $user->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'bank-account.updated',
            'auditable_type' => BankAccount::class,
            'auditable_id' => $bankAccount->id,
        ]);
    }

    public function test_update_preserves_existing_account_number_when_left_blank(): void
    {
        [$org, $ledger] = $this->bankOrganization();
        $bankAccount = BankAccount::factory()->create([
            'organization_id' => $org->id,
            'account_id' => $ledger->id,
            'encrypted_account_number' => '012345678901',
            'masked_account_number' => '••••••••8901',
            'last4' => '8901',
            'created_by' => User::factory()->create()->id,
        ]);
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'update-bank-accounts');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->put("/bank-accounts/{$bankAccount->id}", [
            ...$this->storePayload($ledger->id, $org->base_currency_id),
            'account_number' => '',
        ])->assertRedirect();

        $fresh = $bankAccount->fresh();

        $this->assertSame('012345678901', $fresh->encrypted_account_number);
        $this->assertSame('••••••••8901', $fresh->masked_account_number);
        $this->assertSame('8901', $fresh->last4);
    }

    public function test_update_replaces_account_number_when_provided(): void
    {
        [$org, $ledger] = $this->bankOrganization();
        $bankAccount = BankAccount::factory()->create([
            'organization_id' => $org->id,
            'account_id' => $ledger->id,
            'encrypted_account_number' => '012345678901',
            'masked_account_number' => '••••••••8901',
            'last4' => '8901',
            'created_by' => User::factory()->create()->id,
        ]);
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'update-bank-accounts');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->put("/bank-accounts/{$bankAccount->id}", [
            ...$this->storePayload($ledger->id, $org->base_currency_id),
            'account_number' => '987654321098',
        ])->assertRedirect();

        $fresh = $bankAccount->fresh();

        $this->assertSame('987654321098', $fresh->encrypted_account_number);
        $this->assertSame('••••••••1098', $fresh->masked_account_number);
        $this->assertSame('1098', $fresh->last4);
    }

    public function test_update_cross_organization_bank_account_returns_404(): void
    {
        [$org, $ledger] = $this->bankOrganization();
        $otherOrg = Organization::factory()->create();
        $otherLedger = Account::factory()->create(['organization_id' => $otherOrg->id]);
        $foreignBankAccount = BankAccount::factory()->create([
            'organization_id' => $otherOrg->id,
            'account_id' => $otherLedger->id,
            'created_by' => User::factory()->create()->id,
        ]);

        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'update-bank-accounts');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->put("/bank-accounts/{$foreignBankAccount->id}", $this->storePayload($ledger->id, $org->base_currency_id))
            ->assertStatus(404);
    }

    public function test_update_rejects_cross_organization_ledger_account(): void
    {
        [$org, $ledger] = $this->bankOrganization();
        $bankAccount = BankAccount::factory()->create([
            'organization_id' => $org->id,
            'account_id' => $ledger->id,
            'created_by' => User::factory()->create()->id,
        ]);
        $otherOrgLedger = Account::factory()->create();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'update-bank-accounts');

        $this->actingAs($user);
        $this->confirmPassword();

        // The update path has the same tenant guard as create; without it a
        // bank account could be re-pointed at another org's ledger.
        $this->put("/bank-accounts/{$bankAccount->id}", $this->storePayload($otherOrgLedger->id, $org->base_currency_id))
            ->assertRedirect()
            ->assertSessionHas('error', 'The ledger account must belong to the same organization.');

        $this->assertSame($ledger->id, $bankAccount->fresh()->account_id);
    }

    public function test_update_rejects_non_posting_ledger_account(): void
    {
        [$org, $ledger] = $this->bankOrganization();
        $bankAccount = BankAccount::factory()->create([
            'organization_id' => $org->id,
            'account_id' => $ledger->id,
            'created_by' => User::factory()->create()->id,
        ]);
        $header = Account::factory()->create([
            'organization_id' => $org->id,
            'is_posting_account' => false,
        ]);
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'update-bank-accounts');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->put("/bank-accounts/{$bankAccount->id}", $this->storePayload($header->id, $org->base_currency_id))
            ->assertRedirect()
            ->assertSessionHas('error', 'The ledger account must be an active posting account.');

        $this->assertSame($ledger->id, $bankAccount->fresh()->account_id);
    }

    public function test_tenant_isolation_for_index(): void
    {
        [$org, $ledger] = $this->bankOrganization();
        BankAccount::factory()->create([
            'organization_id' => $org->id,
            'account_id' => $ledger->id,
            'bank_name' => 'My Bank',
            'created_by' => User::factory()->create()->id,
        ]);

        $otherOrg = Organization::factory()->create();
        $otherLedger = Account::factory()->create(['organization_id' => $otherOrg->id]);
        BankAccount::factory()->create([
            'organization_id' => $otherOrg->id,
            'account_id' => $otherLedger->id,
            'bank_name' => 'Foreign Bank',
            'created_by' => User::factory()->create()->id,
        ]);

        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'view-bank-accounts');

        $response = $this->actingAs($user)->get('/bank-accounts');

        $response->assertOk();
        $response->assertSee('My Bank');
        $response->assertDontSee('Foreign Bank');
    }

    public function test_database_constraint_rejects_invalid_status(): void
    {
        [$org, $ledger] = $this->bankOrganization();

        try {
            DB::table('bank_accounts')->insert($this->rawRow($org, $ledger, ['status' => 'frozen']));
        } catch (QueryException $e) {
            $this->assertSame('23514', $e->errorInfo[0]);

            return;
        }

        $this->fail('Expected a CHECK constraint violation for an invalid status.');
    }

    public function test_database_constraint_rejects_negative_opening_balance(): void
    {
        [$org, $ledger] = $this->bankOrganization();

        try {
            DB::table('bank_accounts')->insert($this->rawRow($org, $ledger, ['opening_balance' => -1]));
        } catch (QueryException $e) {
            $this->assertSame('23514', $e->errorInfo[0]);

            return;
        }

        $this->fail('Expected a CHECK constraint violation for a negative opening balance.');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function rawRow(Organization $org, Account $ledger, array $overrides = []): array
    {
        return array_merge([
            'ulid' => (string) Str::ulid(),
            'organization_id' => $org->id,
            'account_id' => $ledger->id,
            'currency_id' => $org->base_currency_id,
            'bank_name' => 'Acme Bank',
            'account_name' => 'Ops',
            'status' => 'active',
            'created_by' => User::factory()->create()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    public function test_audit_chain_verification_remains_valid_after_bank_account_create(): void
    {
        [$org, $ledger] = $this->bankOrganization();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-bank-accounts');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->actingAs($user)->post('/bank-accounts', $this->storePayload($ledger->id, $org->base_currency_id))->assertRedirect();

        $result = app(AuditLogService::class)->verify($org);

        $this->assertTrue($result->valid);
        $this->assertSame(1, $result->checkedCount);
    }
}
