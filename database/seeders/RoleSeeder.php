<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Administrator', 'slug' => 'admin', 'description' => 'Full access to all features.', 'is_global' => false],
            ['name' => 'Accountant', 'slug' => 'accountant', 'description' => 'Create and manage journal entries, invoices, and bills.', 'is_global' => false],
            ['name' => 'Accounting Manager', 'slug' => 'manager', 'description' => 'Review, approve, and post transactions. Close periods.', 'is_global' => false],
            ['name' => 'Auditor', 'slug' => 'auditor', 'description' => 'Read-only access to financial data and audit logs.', 'is_global' => false],
            ['name' => 'Viewer', 'slug' => 'viewer', 'description' => 'Read-only access to authorized reports.', 'is_global' => false],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                $role
            );
        }
    }
}
