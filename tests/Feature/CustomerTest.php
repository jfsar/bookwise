<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
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

    private function payableOrganization(): array
    {
        $org = Organization::factory()->create();
        $receivable = Account::factory()->create(['organization_id' => $org->id]);

        return [$org, $receivable];
    }

    private function storePayload(int $receivableAccountId): array
    {
        return [
            'name' => 'Acme Corp',
            'email' => 'billing@acme.test',
            'phone' => '+15551234567',
            'tax_number' => 'TAX-12345678',
            'payment_terms_days' => 30,
            'credit_limit' => '25000.00',
            'receivable_account_id' => $receivableAccountId,
            'notes' => 'Preferred customer',
            'status' => 'active',
        ];
    }

    public function test_index_requires_authentication(): void
    {
        $this->get('/customers')->assertRedirect(route('login'));
    }

    public function test_index_forbids_user_without_permission(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);

        $response = $this->actingAs($user)->get('/customers');

        $response->assertStatus(403);
    }

    public function test_index_allows_user_with_permission(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'view-customers');

        $this->actingAs($user)->get('/customers')->assertOk();
    }

    public function test_store_forbids_user_without_permission(): void
    {
        [$org, $receivable] = $this->payableOrganization();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);

        $this->actingAs($user);
        $this->confirmPassword();

        $response = $this->post('/customers', $this->storePayload($receivable->id));

        $response->assertForbidden();
        $this->assertDatabaseMissing('customers', ['name' => 'Acme Corp']);
    }

    public function test_store_creates_customer_with_number_and_audit(): void
    {
        [$org, $receivable] = $this->payableOrganization();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-customers');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/customers', $this->storePayload($receivable->id))->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'organization_id' => $org->id,
            'customer_number' => 'CUST-000001',
            'name' => 'Acme Corp',
            'email' => 'billing@acme.test',
            'receivable_account_id' => $receivable->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'customer.created',
            'auditable_type' => Customer::class,
        ]);
    }

    public function test_store_requires_fields(): void
    {
        [$org, $receivable] = $this->payableOrganization();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-customers');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/customers', ['receivable_account_id' => $receivable->id])
            ->assertRedirect()
            ->assertSessionHasErrors(['name', 'payment_terms_days', 'status']);
    }

    public function test_store_rejects_negative_payment_terms(): void
    {
        [$org, $receivable] = $this->payableOrganization();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-customers');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/customers', [...$this->storePayload($receivable->id), 'payment_terms_days' => -1])
            ->assertRedirect()
            ->assertSessionHasErrors('payment_terms_days');
    }

    public function test_store_rejects_invalid_status(): void
    {
        [$org, $receivable] = $this->payableOrganization();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-customers');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/customers', [...$this->storePayload($receivable->id), 'status' => 'frozen'])
            ->assertRedirect()
            ->assertSessionHasErrors('status');
    }

    public function test_store_rejects_cross_organization_receivable_account(): void
    {
        [$org] = $this->payableOrganization();
        $otherOrgReceivable = Account::factory()->create();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-customers');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/customers', $this->storePayload($otherOrgReceivable->id))
            ->assertRedirect()
            ->assertSessionHas('error', 'The receivable account must belong to the same organization.');

        $this->assertDatabaseMissing('customers', ['name' => 'Acme Corp']);
    }

    public function test_store_rejects_non_posting_receivable_account(): void
    {
        [$org] = $this->payableOrganization();
        $header = Account::factory()->create([
            'organization_id' => $org->id,
            'is_posting_account' => false,
        ]);
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-customers');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/customers', $this->storePayload($header->id))
            ->assertRedirect()
            ->assertSessionHas('error', 'The receivable account must be an active posting account.');

        $this->assertDatabaseMissing('customers', ['name' => 'Acme Corp']);
    }

    public function test_update_forbids_user_without_permission(): void
    {
        [$org, $receivable] = $this->payableOrganization();
        $customer = Customer::factory()->create([
            'organization_id' => $org->id,
            'receivable_account_id' => $receivable->id,
            'created_by' => User::factory()->create()->id,
        ]);
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);

        $this->actingAs($user);
        $this->confirmPassword();

        $response = $this->put("/customers/{$customer->id}", $this->storePayload($receivable->id));

        $response->assertForbidden();
    }

    public function test_update_modifies_customer_and_audits(): void
    {
        [$org, $receivable] = $this->payableOrganization();
        $customer = Customer::factory()->create([
            'organization_id' => $org->id,
            'receivable_account_id' => $receivable->id,
            'created_by' => User::factory()->create()->id,
        ]);
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'update-customers');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->put("/customers/{$customer->id}", [...$this->storePayload($receivable->id), 'name' => 'Renamed Corp'])
            ->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Renamed Corp',
            'updated_by' => $user->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'customer.updated',
            'auditable_type' => Customer::class,
            'auditable_id' => $customer->id,
        ]);
    }

    public function test_update_cross_organization_customer_returns_404(): void
    {
        [$org, $receivable] = $this->payableOrganization();
        $otherOrg = Organization::factory()->create();
        $otherOrgReceivable = Account::factory()->create(['organization_id' => $otherOrg->id]);
        $foreignCustomer = Customer::factory()->create([
            'organization_id' => $otherOrg->id,
            'receivable_account_id' => $otherOrgReceivable->id,
            'created_by' => User::factory()->create()->id,
        ]);

        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'update-customers');

        $this->actingAs($user);
        $this->confirmPassword();

        // The route binding must not resolve a customer from another organization.
        $this->put("/customers/{$foreignCustomer->id}", $this->storePayload($receivable->id))
            ->assertStatus(404);
    }

    public function test_update_validates_required_fields(): void
    {
        [$org, $receivable] = $this->payableOrganization();
        $customer = Customer::factory()->create([
            'organization_id' => $org->id,
            'receivable_account_id' => $receivable->id,
            'created_by' => User::factory()->create()->id,
        ]);
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'update-customers');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->put("/customers/{$customer->id}", ['receivable_account_id' => $receivable->id])
            ->assertRedirect()
            ->assertSessionHasErrors(['name', 'payment_terms_days', 'status']);
    }

    public function test_customer_numbers_are_unique_per_organization(): void
    {
        $firstOrg = Organization::factory()->create();
        $firstReceivable = Account::factory()->create(['organization_id' => $firstOrg->id]);
        $secondOrg = Organization::factory()->create();
        $secondReceivable = Account::factory()->create(['organization_id' => $secondOrg->id]);

        Customer::factory()->create([
            'organization_id' => $firstOrg->id,
            'customer_number' => 'CUST-000001',
            'receivable_account_id' => $firstReceivable->id,
            'created_by' => User::factory()->create()->id,
        ]);

        // Same number in a different organization is allowed.
        Customer::factory()->create([
            'organization_id' => $secondOrg->id,
            'customer_number' => 'CUST-000001',
            'receivable_account_id' => $secondReceivable->id,
            'created_by' => User::factory()->create()->id,
        ]);

        $this->assertSame(1, Customer::where('organization_id', $firstOrg->id)->where('customer_number', 'CUST-000001')->count());
        $this->assertSame(1, Customer::where('organization_id', $secondOrg->id)->where('customer_number', 'CUST-000001')->count());
    }

    public function test_tenant_isolation_for_index(): void
    {
        $org = Organization::factory()->create();
        $receivable = Account::factory()->create(['organization_id' => $org->id]);
        Customer::factory()->create([
            'organization_id' => $org->id,
            'receivable_account_id' => $receivable->id,
            'created_by' => User::factory()->create()->id,
        ]);

        $otherOrg = Organization::factory()->create();
        $otherReceivable = Account::factory()->create(['organization_id' => $otherOrg->id]);
        Customer::factory()->create([
            'organization_id' => $otherOrg->id,
            'receivable_account_id' => $otherReceivable->id,
            'created_by' => User::factory()->create()->id,
        ]);

        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'view-customers');

        $response = $this->actingAs($user)->get('/customers');
        $response->assertOk();
        $this->assertDatabaseCount('customers', 2);
    }

    public function test_audit_chain_verification_remains_valid_after_customer_create(): void
    {
        [$org, $receivable] = $this->payableOrganization();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-customers');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->actingAs($user)->post('/customers', $this->storePayload($receivable->id))->assertRedirect();

        $result = app(AuditLogService::class)->verify($org);

        $this->assertTrue($result->valid);
        $this->assertSame(1, $result->checkedCount);
    }
}
