<?php

namespace Database\Seeders;

use App\Actions\Organization\SwitchOrganization;
use App\Enums\MembershipStatus;
use App\Models\Currency;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => 'password',
            ],
        );

        $admin->forceFill([
            'email_verified_at' => now(),
            'status' => 'active',
        ])->save();

        $organization = Organization::updateOrCreate(
            ['code' => 'ACME'],
            [
                'name' => 'Acme Corporation',
                'display_name' => 'Acme Corp',
                'ulid' => Str::ulid(),
                'country_code' => 'US',
                'base_currency_id' => Currency::where('code', 'USD')->first()?->id,
                'timezone' => 'America/New_York',
                'fiscal_year_start_month' => 1,
                'date_format' => 'Y-m-d',
                'created_by' => $admin->id,
            ],
        );

        $organization->users()->syncWithoutDetaching([
            $admin->id => [
                'membership_status' => MembershipStatus::Active,
                'joined_at' => now(),
            ],
        ]);

        $adminRole = Role::where('slug', 'admin')->first();

        if ($adminRole) {
            $admin->roles()->syncWithoutDetaching([
                $adminRole->id => [
                    'organization_id' => $organization->id,
                ],
            ]);
        }

        SwitchOrganization::current($admin);
    }
}
