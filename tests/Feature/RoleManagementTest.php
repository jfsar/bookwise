<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private function attachManageUsers(User $user, Organization $organization): void
    {
        $role = Role::create([
            'name' => 'Manager Tester',
            'slug' => 'manager-tester-'.uniqid(),
            'is_global' => false,
        ]);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'manage-users'])->id);

        $user->roles()->attach($role->id, ['organization_id' => $organization->id]);
    }

    private function createPermission(string $name): Permission
    {
        return Permission::firstOrCreate(['name' => $name]);
    }

    public function test_create_role_forbids_user_without_permission(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($organization->id, ['membership_status' => 'active']);

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/settings/roles', ['name' => 'Custom Role'])
            ->assertForbidden();
    }

    public function test_create_role_with_permissions(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($organization->id, ['membership_status' => 'active']);
        $this->attachManageUsers($user, $organization);

        $view = $this->createPermission('view-accounts');
        $create = $this->createPermission('create-accounts');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->post('/settings/roles', [
            'name' => 'Custom Role',
            'permission_ids' => [$view->id, $create->id],
        ])
            ->assertRedirect();

        $role = Role::where('slug', 'custom-role')->where('organization_id', $organization->id)->first();

        $this->assertNotNull($role);
        $this->assertFalse($role->is_global);
        $this->assertCount(2, $role->permissions);
    }

    public function test_update_role_syncs_permissions(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($organization->id, ['membership_status' => 'active']);
        $this->attachManageUsers($user, $organization);

        $permission = $this->createPermission('view-accounts');
        $role = Role::create([
            'name' => 'Custom Role',
            'slug' => 'custom-role-'.uniqid(),
            'organization_id' => $organization->id,
            'is_global' => false,
        ]);
        $role->permissions()->attach($permission->id);

        $other = $this->createPermission('view-reports');

        $this->actingAs($user);
        $this->confirmPassword();

        $this->put("/settings/roles/{$role->id}", [
            'name' => 'Updated Role',
            'permission_ids' => [$other->id],
        ])
            ->assertRedirect();

        $fresh = $role->fresh();
        $this->assertSame('Updated Role', $fresh->name);
        $this->assertTrue($fresh->permissions()->where('permissions.id', $other->id)->exists());
        $this->assertFalse($fresh->permissions()->where('permissions.id', $permission->id)->exists());
    }

    public function test_global_role_cannot_be_deleted(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($organization->id, ['membership_status' => 'active']);
        $this->attachManageUsers($user, $organization);

        $role = Role::create([
            'name' => 'System Role',
            'slug' => 'system-role-'.uniqid(),
            'is_global' => true,
        ]);

        $this->actingAs($user);
        $this->confirmPassword();

        $this->delete("/settings/roles/{$role->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_custom_role_can_be_deleted(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($organization->id, ['membership_status' => 'active']);
        $this->attachManageUsers($user, $organization);

        $role = Role::create([
            'name' => 'Custom Role',
            'slug' => 'custom-role-'.uniqid(),
            'organization_id' => $organization->id,
            'is_global' => false,
        ]);

        $this->actingAs($user);
        $this->confirmPassword();

        $this->delete("/settings/roles/{$role->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }
}
