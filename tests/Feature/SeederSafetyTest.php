<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_seeder_creates_admin_when_none_exists(): void
    {
        (new RoleSeeder)->run();
        (new AdminUserSeeder)->run();

        $admin = User::where('email', 'admin@example.com')->first();

        $this->assertNotNull($admin);
        $this->assertSame('Admin User', $admin->name);
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertSame('active', $admin->status);
    }

    public function test_admin_user_seeder_does_not_overwrite_existing_password(): void
    {
        $originalPassword = 'super-secret-custom-password-42';

        User::factory()->create([
            'email' => 'admin@example.com',
            'name' => 'Real Admin',
            'password' => $originalPassword,
        ]);

        (new RoleSeeder)->run();
        (new AdminUserSeeder)->run();

        $admin = User::where('email', 'admin@example.com')->first();

        $this->assertSame('Real Admin', $admin->name);
        $this->assertTrue(Hash::check($originalPassword, $admin->password));
        $this->assertSame('active', $admin->status);
    }

    public function test_permission_seeder_does_not_wipe_custom_role_grants(): void
    {
        (new RoleSeeder)->run();
        (new PermissionSeeder)->run();

        $viewer = Role::where('slug', 'viewer')->first();
        $extraPermission = Permission::where('name', 'view-journal-entries')->first();

        $viewer->permissions()->attach($extraPermission->id);

        $this->assertDatabaseHas('permission_role', [
            'role_id' => $viewer->id,
            'permission_id' => $extraPermission->id,
        ]);

        (new RoleSeeder)->run();
        (new PermissionSeeder)->run();

        $this->assertDatabaseHas('permission_role', [
            'role_id' => $viewer->id,
            'permission_id' => $extraPermission->id,
        ]);
    }
}
