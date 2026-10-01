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
            ['name' => 'allocate-vendor-payments', 'description' => 'Allocate vendor payments to bills.'],
            ['name' => 'approve-vendor-payments', 'description' => 'Post vendor payments to the ledger.'],
            ['name' => 'void-vendor-payments', 'description' => 'Void draft or posted vendor payments.'],
            ['name' => 'view-vendor-debit-notes', 'description' => 'View vendor debit notes.'],
            ['name' => 'create-vendor-debit-notes', 'description' => 'Create vendor debit notes.'],
            ['name' => 'approve-vendor-debit-notes', 'description' => 'Post vendor debit notes to the ledger.'],
            ['name' => 'void-vendor-debit-notes', 'description' => 'Void draft vendor debit notes.'],
            ['name' => 'view-bank-accounts', 'description' => 'View bank accounts.'],
            ['name' => 'create-bank-accounts', 'description' => 'Create new bank accounts.'],
            ['name' => 'update-bank-accounts', 'description' => 'Update existing bank accounts.'],
            ['name' => 'archive-bank-accounts', 'description' => 'Deactivate/close bank accounts.'],
            ['name' => 'view-bank-transactions', 'description' => 'View bank transactions.'],
            ['name' => 'create-bank-transactions', 'description' => 'Record manual bank transactions.'],
            ['name' => 'update-bank-transactions', 'description' => 'Edit unreconciled manual bank transactions.'],
            ['name' => 'import-bank-transactions', 'description' => 'Import bank statement files.'],
            ['name' => 'view-bank-reconciliations', 'description' => 'View bank reconciliations.'],
            ['name' => 'manage-bank-reconciliations', 'description' => 'Start reconciliations and match bank transactions.'],
            ['name' => 'finalize-bank-reconciliations', 'description' => 'Finalize and reopen bank reconciliations.'],
            ['name' => 'view-recurring-journals', 'description' => 'View recurring journal definitions and runs.'],
            ['name' => 'create-recurring-journals', 'description' => 'Create recurring journal definitions.'],
            ['name' => 'update-recurring-journals', 'description' => 'Update, pause, resume and run recurring journal definitions.'],
            ['name' => 'delete-recurring-journals', 'description' => 'Delete recurring journal definitions that have never run.'],
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
                'allocate-vendor-payments',
                'view-vendor-debit-notes',
                'create-vendor-debit-notes',
                'view-bank-accounts',
                'create-bank-accounts',
                'update-bank-accounts',
                'view-bank-transactions',
                'create-bank-transactions',
                'update-bank-transactions',
                'import-bank-transactions',
                'view-bank-reconciliations',
                'manage-bank-reconciliations',
                'finalize-bank-reconciliations',
                'view-recurring-journals',
                'create-recurring-journals',
                'update-recurring-journals',
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
                'view-vendor-debit-notes',
                'view-bank-accounts',
                'view-bank-transactions',
                'view-bank-reconciliations',
                'view-recurring-journals',
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
