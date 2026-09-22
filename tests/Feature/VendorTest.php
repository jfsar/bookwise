<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorTest extends TestCase
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
        $payable = Account::factory()->create(['organization_id' => $org->id]);

        return [$org, $payable];
    }

    private function storePayload(int $payableAccountId): array
    {
        return [
            'name' => 'Acme Supplies',
            'email' => 'vendor@acme.test',
            'phone' => '+15559876543',
            'tax_number' => 'TAX-87654321',
            'payment_terms_days' => 30,
            'payable_account_id' => $payableAccountId,
            'notes' => 'Preferred vendor',
            'status' => 'active',
        ];
    }

    public function test_index_requires_authentication(): void
    {
        $this->get('/vendors')->assertRedirect(route('login'));
    }

    public function test_index_forbids_user_without_permission(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);

        $response = $this->actingAs($user)->get('/vendors');

        $response->assertStatus(403);
    }

    public function test_index_allows_user_with_permission(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'view-vendors');

        $this->actingAs($user)->get('/vendors')->assertOk();
    }

    public function test_store_forbids_user_without_permission(): void
    {
        [$org, $payable] = $this->payableOrganization();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);

        $this->actingAs($user);
        $this->confirmPassword();

        $response = $this->post('/vendors', $this->storePayload($payable->id));

        $response->assertForbidden();
        $this->assertDatabaseMissing('vendors', ['name' => 'Acme Supplies']);
    }

    public function test_store_creates_vendor_with_number_and_audit(): void
    {
        [$org, $payable] = $this->payableOrganization();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-vendors');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/vendors', $this->storePayload($payable->id))->assertRedirect();

        $this->assertDatabaseHas('vendors', [
            'organization_id' => $org->id,
            'vendor_number' => 'VEND-000001',
            'name' => 'Acme Supplies',
            'email' => 'vendor@acme.test',
            'payable_account_id' => $payable->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'vendor.created',
            'auditable_type' => Vendor::class,
        ]);
    }

    public function test_store_requires_fields(): void
    {
        [$org, $payable] = $this->payableOrganization();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-vendors');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/vendors', ['payable_account_id' => $payable->id])
            ->assertRedirect()
            ->assertSessionHasErrors(['name', 'payment_terms_days', 'status']);
    }

    public function test_store_rejects_negative_payment_terms(): void
    {
        [$org, $payable] = $this->payableOrganization();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-vendors');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/vendors', [...$this->storePayload($payable->id), 'payment_terms_days' => -1])
            ->assertRedirect()
            ->assertSessionHasErrors('payment_terms_days');
    }

    public function test_store_rejects_invalid_status(): void
    {
        [$org, $payable] = $this->payableOrganization();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-vendors');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/vendors', [...$this->storePayload($payable->id), 'status' => 'frozen'])
            ->assertRedirect()
            ->assertSessionHasErrors('status');
    }

    public function test_store_rejects_cross_organization_payable_account(): void
    {
        [$org] = $this->payableOrganization();
        $otherOrgPayable = Account::factory()->create();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-vendors');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/vendors', $this->storePayload($otherOrgPayable->id))
            ->assertRedirect()
            ->assertSessionHas('error', 'The payable account must belong to the same organization.');

        $this->assertDatabaseMissing('vendors', ['name' => 'Acme Supplies']);
    }

    public function test_store_rejects_non_posting_payable_account(): void
    {
        [$org] = $this->payableOrganization();
        $header = Account::factory()->create([
            'organization_id' => $org->id,
            'is_posting_account' => false,
        ]);
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-vendors');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/vendors', $this->storePayload($header->id))
            ->assertRedirect()
            ->assertSessionHas('error', 'The payable account must be an active posting account.');

        $this->assertDatabaseMissing('vendors', ['name' => 'Acme Supplies']);
    }

    public function test_update_forbids_user_without_permission(): void
    {
        [$org, $payable] = $this->payableOrganization();
        $vendor = Vendor::factory()->create([
            'organization_id' => $org->id,
            'payable_account_id' => $payable->id,
            'created_by' => User::factory()->create()->id,
        ]);
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);

        $this->actingAs($user);
        $this->confirmPassword();

        $response = $this->put("/vendors/{$vendor->id}", $this->storePayload($payable->id));

        $response->assertForbidden();
    }

    public function test_update_modifies_vendor_and_audits(): void
    {
        [$org, $payable] = $this->payableOrganization();
        $vendor = Vendor::factory()->create([
            'organization_id' => $org->id,
            'payable_account_id' => $payable->id,
            'created_by' => User::factory()->create()->id,
        ]);
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'update-vendors');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->put("/vendors/{$vendor->id}", [...$this->storePayload($payable->id), 'name' => 'Renamed Supplies'])
            ->assertRedirect();

        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'name' => 'Renamed Supplies',
            'updated_by' => $user->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'vendor.updated',
            'auditable_type' => Vendor::class,
            'auditable_id' => $vendor->id,
        ]);
    }

    public function test_update_cross_organization_vendor_returns_404(): void
    {
        [$org, $payable] = $this->payableOrganization();
        $otherOrg = Organization::factory()->create();
        $otherOrgPayable = Account::factory()->create(['organization_id' => $otherOrg->id]);
        $foreignVendor = Vendor::factory()->create([
            'organization_id' => $otherOrg->id,
            'payable_account_id' => $otherOrgPayable->id,
            'created_by' => User::factory()->create()->id,
        ]);

        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'update-vendors');

        $this->actingAs($user);
        $this->confirmPassword();

        // The route binding must not resolve a vendor from another organization.
        $this->put("/vendors/{$foreignVendor->id}", $this->storePayload($payable->id))
            ->assertStatus(404);
    }

    public function test_update_validates_required_fields(): void
    {
        [$org, $payable] = $this->payableOrganization();
        $vendor = Vendor::factory()->create([
            'organization_id' => $org->id,
            'payable_account_id' => $payable->id,
            'created_by' => User::factory()->create()->id,
        ]);
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'update-vendors');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->put("/vendors/{$vendor->id}", ['payable_account_id' => $payable->id])
            ->assertRedirect()
            ->assertSessionHasErrors(['name', 'payment_terms_days', 'status']);
    }

    public function test_vendor_numbering_increments_per_organization(): void
    {
        [$org, $payable] = $this->payableOrganization();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-vendors');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/vendors', $this->storePayload($payable->id))->assertRedirect();
        $this->post('/vendors', $this->storePayload($payable->id))->assertRedirect();

        $numbers = Vendor::forOrganization($org)->orderBy('id')->pluck('vendor_number')->all();

        $this->assertSame(['VEND-000001', 'VEND-000002'], $numbers);
    }

    public function test_vendor_numbers_are_unique_per_organization(): void
    {
        $firstOrg = Organization::factory()->create();
        $firstPayable = Account::factory()->create(['organization_id' => $firstOrg->id]);
        $secondOrg = Organization::factory()->create();
        $secondPayable = Account::factory()->create(['organization_id' => $secondOrg->id]);

        Vendor::factory()->create([
            'organization_id' => $firstOrg->id,
            'vendor_number' => 'VEND-000001',
            'payable_account_id' => $firstPayable->id,
            'created_by' => User::factory()->create()->id,
        ]);

        // Same number in a different organization is allowed.
        Vendor::factory()->create([
            'organization_id' => $secondOrg->id,
            'vendor_number' => 'VEND-000001',
            'payable_account_id' => $secondPayable->id,
            'created_by' => User::factory()->create()->id,
        ]);

        $this->assertSame(1, Vendor::where('organization_id', $firstOrg->id)->where('vendor_number', 'VEND-000001')->count());
        $this->assertSame(1, Vendor::where('organization_id', $secondOrg->id)->where('vendor_number', 'VEND-000001')->count());
    }

    public function test_tenant_isolation_for_index(): void
    {
        $org = Organization::factory()->create();
        $payable = Account::factory()->create(['organization_id' => $org->id]);
        Vendor::factory()->create([
            'organization_id' => $org->id,
            'payable_account_id' => $payable->id,
            'created_by' => User::factory()->create()->id,
        ]);

        $otherOrg = Organization::factory()->create();
        $otherPayable = Account::factory()->create(['organization_id' => $otherOrg->id]);
        Vendor::factory()->create([
            'organization_id' => $otherOrg->id,
            'payable_account_id' => $otherPayable->id,
            'created_by' => User::factory()->create()->id,
        ]);

        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'view-vendors');

        $response = $this->actingAs($user)->get('/vendors');
        $response->assertOk();
        $this->assertDatabaseCount('vendors', 2);
    }

    public function test_audit_chain_verification_remains_valid_after_vendor_create(): void
    {
        [$org, $payable] = $this->payableOrganization();
        $user = User::factory()->create();
        $this->attachRoleWithPermission($user, $org, 'create-vendors');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->actingAs($user)->post('/vendors', $this->storePayload($payable->id))->assertRedirect();

        $result = app(AuditLogService::class)->verify($org);

        $this->assertTrue($result->valid);
        $this->assertSame(1, $result->checkedCount);
    }
}
