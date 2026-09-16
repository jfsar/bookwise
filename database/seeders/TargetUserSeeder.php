<?php

namespace Database\Seeders;

use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class TargetUserSeeder extends Seeder
{
    /**
     * Target user personas from the SRS, mapped to their role slugs.
     *
     * @var array<int, array{email: string, name: string, role: string}>
     */
    private array $targetUsers = [
        ['email' => 'admin@example.com', 'name' => 'Admin User', 'role' => 'admin'],
        ['email' => 'accountant@example.com', 'name' => 'Accountant User', 'role' => 'accountant'],
        ['email' => 'manager@example.com', 'name' => 'Accounting Manager', 'role' => 'manager'],
        ['email' => 'auditor@example.com', 'name' => 'Auditor User', 'role' => 'auditor'],
        ['email' => 'viewer@example.com', 'name' => 'Viewer User', 'role' => 'viewer'],
    ];

    public function run(): void
    {
        $organizations = Organization::all();

        if ($organizations->isEmpty()) {
            $this->command?->warn('No organizations found; TargetUserSeeder skipped.');

            return;
        }

        foreach ($this->targetUsers as $targetUser) {
            $user = User::firstOrNew(
                ['email' => $targetUser['email']],
                ['name' => $targetUser['name'], 'password' => 'password'],
            );

            if (! $user->exists) {
                $user->save();
            }

            $user->forceFill([
                'email_verified_at' => now(),
                'status' => 'active',
            ])->save();

            $role = Role::where('slug', $targetUser['role'])->first();

            if (! $role) {
                continue;
            }

            $this->attachToOrganizations($user, $role, $organizations);
        }
    }

    private function attachToOrganizations(User $user, Role $role, $organizations): void
    {
        foreach ($organizations as $organization) {
            $user->organizations()->syncWithoutDetaching([
                $organization->id => [
                    'membership_status' => MembershipStatus::Active,
                    'joined_at' => now(),
                ],
            ]);

            $user->roles()->syncWithoutDetaching([
                $role->id => ['organization_id' => $organization->id],
            ]);
        }
    }
}
