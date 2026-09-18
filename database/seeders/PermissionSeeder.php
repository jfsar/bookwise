<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'view-dashboard', 'description' => 'View the dashboard.'],
            ['name' => 'view-accounts', 'description' => 'View chart of accounts.'],
            ['name' => 'create-accounts', 'description' => 'Create new accounts.'],
            ['name' => 'update-accounts', 'description' => 'Update existing accounts.'],
            ['name' => 'archive-accounts', 'description' => 'Archive/deactivate accounts.'],
            ['name' => 'view-journal-entries', 'description' => 'View journal entries.'],
            ['name' => 'create-journal-entries', 'description' => 'Create and save draft journal entries.'],
            ['name' => 'submit-journal-entries', 'description' => 'Submit journal entries for approval.'],
            ['name' => 'approve-journal-entries', 'description' => 'Approve submitted journal entries.'],
            ['name' => 'reject-journal-entries', 'description' => 'Reject submitted journal entries.'],
            ['name' => 'post-journal-entries', 'description' => 'Post approved journal entries to the ledger.'],
            ['name' => 'reverse-journal-entries', 'description' => 'Reverse posted journal entries.'],
            ['name' => 'cancel-journal-entries', 'description' => 'Cancel draft or submitted journal entries.'],
            ['name' => 'view-fiscal-years', 'description' => 'View fiscal years and periods.'],
            ['name' => 'manage-fiscal-years', 'description' => 'Create and manage fiscal years.'],
            ['name' => 'view-accounting-periods', 'description' => 'View accounting periods.'],
            ['name' => 'close-accounting-period', 'description' => 'Close accounting periods.'],
            ['name' => 'reopen-accounting-period', 'description' => 'Reopen closed accounting periods.'],
            ['name' => 'view-reports', 'description' => 'View financial reports.'],
            ['name' => 'view-audit-logs', 'description' => 'View audit logs.'],
            ['name' => 'manage-users', 'description' => 'Manage users and memberships.'],
            ['name' => 'manage-settings', 'description' => 'Manage organization settings.'],
            ['name' => 'view-customers', 'description' => 'View customers.'],
            ['name' => 'create-customers', 'description' => 'Create new customers.'],
            ['name' => 'update-customers', 'description' => 'Update existing customers.'],
            ['name' => 'archive-customers', 'description' => 'Archive/deactivate customers.'],
            ['name' => 'view-invoices', 'description' => 'View customer invoices.'],
            ['name' => 'create-invoices', 'description' => 'Create customer invoices.'],
            ['name' => 'approve-invoices', 'description' => 'Approve and post customer invoices.'],
            ['name' => 'void-invoices', 'description' => 'Void draft, posted or partially paid customer invoices.'],
            ['name' => 'view-payments', 'description' => 'View customer payments.'],
            ['name' => 'create-payments', 'description' => 'Create customer payments.'],
            ['name' => 'allocate-payments', 'description' => 'Allocate customer payments to invoices.'],
            ['name' => 'approve-payments', 'description' => 'Post customer payments to the ledger.'],
            ['name' => 'void-payments', 'description' => 'Void draft or posted customer payments.'],
            ['name' => 'view-credit-notes', 'description' => 'View customer credit notes.'],
            ['name' => 'create-credit-notes', 'description' => 'Create customer credit notes.'],
            ['name' => 'approve-credit-notes', 'description' => 'Post customer credit notes to the ledger.'],
            ['name' => 'void-credit-notes', 'description' => 'Void draft customer credit notes.'],
            ['name' => 'view-vendors', 'description' => 'View vendors.'],
            ['name' => 'create-vendors', 'description' => 'Create new vendors.'],
            ['name' => 'update-vendors', 'description' => 'Update existing vendors.'],
            ['name' => 'archive-vendors', 'description' => 'Archive/deactivate vendors.'],
            ['name' => 'view-bills', 'description' => 'View vendor bills.'],
            ['name' => 'create-bills', 'description' => 'Create vendor bills.'],
            ['name' => 'approve-bills', 'description' => 'Approve and post vendor bills.'],
            ['name' => 'void-bills', 'description' => 'Void draft, posted or partially paid vendor bills.'],
            ['name' => 'view-vendor-payments', 'description' => 'View vendor payments.'],
            ['name' => 'create-vendor-payments', 'description' => 'Create vendor payments.'],
            ['name' => 'approve-vendor-payments', 'description' => 'Post vendor payments to the ledger.'],
            ['name' => 'void-vendor-payments', 'description' => 'Void draft or posted vendor payments.'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }

        $this->assignDefaultPermissions();
    }

    protected function assignDefaultPermissions(): void
    {
        $admin = Role::where('slug', 'admin')->first();
        if ($admin) {
            $admin->permissions()->syncWithoutDetaching(Permission::all());
        }

        $manager = Role::where('slug', 'manager')->first();
        if ($manager) {
            $manager->permissions()->syncWithoutDetaching(Permission::whereNotIn('name', [
                'manage-users',
                'manage-settings',
            ])->get());
        }

        $accountant = Role::where('slug', 'accountant')->first();
        if ($accountant) {
            $accountant->permissions()->syncWithoutDetaching(Permission::whereIn('name', [
                'view-dashboard',
                'view-accounts',
                'create-accounts',
                'update-accounts',
                'view-journal-entries',
                'create-journal-entries',
                'submit-journal-entries',
                'view-fiscal-years',
                'view-accounting-periods',
                'view-reports',
                'view-customers',
                'create-customers',
                'update-customers',
                'view-invoices',
                'create-invoices',
                'view-payments',
                'create-payments',
                'allocate-payments',
                'view-credit-notes',
                'create-credit-notes',
                'view-vendors',
                'create-vendors',
                'update-vendors',
                'view-bills',
                'create-bills',
                'view-vendor-payments',
                'create-vendor-payments',
            ])->get());
        }

        $auditor = Role::where('slug', 'auditor')->first();
        if ($auditor) {
            $auditor->permissions()->syncWithoutDetaching(Permission::whereIn('name', [
                'view-dashboard',
                'view-accounts',
                'view-journal-entries',
                'view-fiscal-years',
                'view-accounting-periods',
                'view-reports',
                'view-audit-logs',
                'view-customers',
                'view-invoices',
                'view-payments',
                'view-credit-notes',
                'view-vendors',
                'view-bills',
                'view-vendor-payments',
            ])->get());
        }

        $viewer = Role::where('slug', 'viewer')->first();
        if ($viewer) {
            $viewer->permissions()->syncWithoutDetaching(Permission::whereIn('name', [
                'view-dashboard',
                'view-reports',
            ])->get());
        }
    }
}
