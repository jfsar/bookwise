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
            $admin->permissions()->sync(Permission::all());
        }

        $manager = Role::where('slug', 'manager')->first();
        if ($manager) {
            $manager->permissions()->sync(Permission::whereNotIn('name', [
                'manage-users',
                'manage-settings',
            ])->get());
        }

        $accountant = Role::where('slug', 'accountant')->first();
        if ($accountant) {
            $accountant->permissions()->sync(Permission::whereIn('name', [
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
            ])->get());
        }

        $auditor = Role::where('slug', 'auditor')->first();
        if ($auditor) {
            $auditor->permissions()->sync(Permission::whereIn('name', [
                'view-dashboard',
                'view-accounts',
                'view-journal-entries',
                'view-fiscal-years',
                'view-accounting-periods',
                'view-reports',
                'view-audit-logs',
            ])->get());
        }

        $viewer = Role::where('slug', 'viewer')->first();
        if ($viewer) {
            $viewer->permissions()->sync(Permission::whereIn('name', [
                'view-dashboard',
                'view-reports',
            ])->get());
        }
    }
}
