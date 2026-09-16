<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CurrencySeeder::class,
            AccountTypeSeeder::class,
            RoleSeeder::class,
            PermissionSeeder::class,
            AdminUserSeeder::class,
            TargetUserSeeder::class,
            JournalSeeder::class,
        ]);
    }
}
