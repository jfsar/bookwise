<?php

namespace Database\Seeders;

use App\Actions\Organization\CreateOrganization;
use App\Actions\Organization\SwitchOrganization;
use App\Models\Currency;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            CurrencySeeder::class,
            AccountTypeSeeder::class,
            RoleSeeder::class,
            PermissionSeeder::class,
        ]);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $createOrg = new CreateOrganization;

        $organization = $createOrg->run([
            'name' => 'Acme Corporation',
            'display_name' => 'Acme Corp',
            'code' => 'ACME',
            'country_code' => 'US',
            'base_currency_id' => Currency::where('code', 'USD')->first()->id,
            'timezone' => 'America/New_York',
            'fiscal_year_start_month' => 1,
        ], $user);

        $adminRole = Role::where('slug', 'admin')->first();
        if ($adminRole) {
            $user->roles()->attach($adminRole->id, [
                'organization_id' => $organization->id,
            ]);
        }

        SwitchOrganization::current($user);

        $this->call([
            JournalSeeder::class,
        ]);
    }
}
