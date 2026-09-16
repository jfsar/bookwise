<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TargetUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TargetUserSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedTargetUsers(): Organization
    {
        (new RoleSeeder)->run();
        (new PermissionSeeder)->run();
        (new CurrencySeeder)->run();
        (new AdminUserSeeder)->run();
        (new TargetUserSeeder)->run();

        return Organization::where('code', 'ACME')->firstOrFail();
    }

    public function test_creates_all_target_users_with_roles_in_same_organization(): void
    {
        $organization = $this->seedTargetUsers();

        $expected = [
            'admin@example.com' => ['name' => 'Admin User', 'role' => 'admin'],
            'accountant@example.com' => ['name' => 'Accountant User', 'role' => 'accountant'],
            'manager@example.com' => ['name' => 'Accounting Manager', 'role' => 'manager'],
            'auditor@example.com' => ['name' => 'Auditor User', 'role' => 'auditor'],
            'viewer@example.com' => ['name' => 'Viewer User', 'role' => 'viewer'],
        ];

        foreach ($expected as $email => $attrs) {
            $user = User::where('email', $email)->first();

            $this->assertNotNull($user, "Target user {$email} was not created.");
            $this->assertSame($attrs['name'], $user->name);
            $this->assertSame('active', $user->status);
            $this->assertNotNull($user->email_verified_at);
            $this->assertTrue($user->belongsToOrganization($organization));
            $this->assertSame([$organization->id], $user->organizations()->pluck('organizations.id')->all());
            $this->assertTrue($user->hasRole($attrs['role'], $organization));
        }
    }

    public function test_assigns_right_permissions_via_role(): void
    {
        $organization = $this->seedTargetUsers();

        $accountant = User::where('email', 'accountant@example.com')->first();
        $this->assertTrue($accountant->hasPermissionTo('create-journal-entries', $organization));
        $this->assertFalse($accountant->hasPermissionTo('approve-journal-entries', $organization));

        $admin = User::where('email', 'admin@example.com')->first();
        $this->assertTrue($admin->hasPermissionTo('manage-users', $organization));
        $this->assertTrue($admin->hasPermissionTo('manage-settings', $organization));

        $auditor = User::where('email', 'auditor@example.com')->first();
        $this->assertTrue($auditor->hasPermissionTo('view-audit-logs', $organization));
        $this->assertFalse($auditor->hasPermissionTo('create-journal-entries', $organization));

        $viewer = User::where('email', 'viewer@example.com')->first();
        $this->assertTrue($viewer->hasPermissionTo('view-reports', $organization));
        $this->assertFalse($viewer->hasPermissionTo('view-journal-entries', $organization));
    }

    public function test_does_not_overwrite_existing_passwords_and_names(): void
    {
        $customPassword = 'my-custom-password-123';

        User::factory()->create([
            'email' => 'accountant@example.com',
            'name' => 'Real Accountant',
            'password' => $customPassword,
        ]);

        $this->seedTargetUsers();

        $user = User::where('email', 'accountant@example.com')->first();

        $this->assertSame('Real Accountant', $user->name);
        $this->assertTrue(Hash::check($customPassword, $user->password));
    }

    public function test_is_idempotent_on_rerun(): void
    {
        $this->seedTargetUsers();

        $roleUserCount = DB::table('role_user')->count();
        $organizationUserCount = DB::table('organization_user')->count();

        (new TargetUserSeeder)->run();

        $this->assertSame($roleUserCount, DB::table('role_user')->count());
        $this->assertSame($organizationUserCount, DB::table('organization_user')->count());
    }

    public function test_skips_when_no_organizations_exist(): void
    {
        (new RoleSeeder)->run();
        (new TargetUserSeeder)->run();

        $this->assertSame(0, User::count());
    }
}
