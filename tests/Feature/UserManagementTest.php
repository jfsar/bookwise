<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
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

    public function test_create_user_forbids_user_without_permission(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($organization->id, ['membership_status' => 'active']);

        $this->actingAs($user)
            ->post('/settings/users', [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertForbidden();
    }

    public function test_create_user_adds_member_with_role(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($organization->id, ['membership_status' => 'active']);
        $this->attachManageUsers($user, $organization);

        $role = Role::create([
            'name' => 'Bookkeeper',
            'slug' => 'bookkeeper-'.uniqid(),
            'organization_id' => $organization->id,
            'is_global' => false,
        ]);

        $this->actingAs($user)
            ->post('/settings/users', [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_id' => $role->id,
            ])
            ->assertRedirect();

        $created = User::where('email', 'jane@example.com')->first();

        $this->assertNotNull($created);
        $this->assertTrue($created->organizations()->where('organization_id', $organization->id)->exists());
        $this->assertTrue($created->roles()->where('roles.id', $role->id)->wherePivot('organization_id', $organization->id)->exists());
    }

    public function test_update_user_can_deactivate(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create();
        $admin->organizations()->attach($organization->id, ['membership_status' => 'active']);
        $this->attachManageUsers($admin, $organization);

        $target = User::factory()->create();
        $target->organizations()->attach($organization->id, ['membership_status' => 'active']);

        $this->actingAs($admin)
            ->put("/settings/users/{$target->id}", [
                'name' => $target->name,
                'email' => $target->email,
                'status' => 'inactive',
            ])
            ->assertRedirect();

        $this->assertSame('inactive', $target->fresh()->status);
    }

    public function test_update_user_cannot_deactivate_self(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create();
        $admin->organizations()->attach($organization->id, ['membership_status' => 'active']);
        $this->attachManageUsers($admin, $organization);

        $this->actingAs($admin)
            ->put("/settings/users/{$admin->id}", [
                'name' => $admin->name,
                'email' => $admin->email,
                'status' => 'inactive',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $this->assertSame('active', $admin->fresh()->status);
    }
}
