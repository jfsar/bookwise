<?php

namespace Database\Seeders;

use App\Models\AccountType;
use Illuminate\Database\Seeder;

class AccountTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'asset', 'name' => 'Asset', 'category' => 'asset', 'normal_balance' => 'debit', 'sort_order' => 1],
            ['code' => 'liability', 'name' => 'Liability', 'category' => 'liability', 'normal_balance' => 'credit', 'sort_order' => 2],
            ['code' => 'equity', 'name' => 'Equity', 'category' => 'equity', 'normal_balance' => 'credit', 'sort_order' => 3],
            ['code' => 'revenue', 'name' => 'Revenue', 'category' => 'revenue', 'normal_balance' => 'credit', 'sort_order' => 4],
            ['code' => 'expense', 'name' => 'Expense', 'category' => 'expense', 'normal_balance' => 'debit', 'sort_order' => 5],
            ['code' => 'contra_asset', 'name' => 'Contra Asset', 'category' => 'asset', 'normal_balance' => 'credit', 'sort_order' => 6],
            ['code' => 'contra_liability', 'name' => 'Contra Liability', 'category' => 'liability', 'normal_balance' => 'debit', 'sort_order' => 7],
            ['code' => 'contra_equity', 'name' => 'Contra Equity', 'category' => 'equity', 'normal_balance' => 'debit', 'sort_order' => 8],
            ['code' => 'contra_revenue', 'name' => 'Contra Revenue', 'category' => 'revenue', 'normal_balance' => 'debit', 'sort_order' => 9],
            ['code' => 'contra_expense', 'name' => 'Contra Expense', 'category' => 'expense', 'normal_balance' => 'credit', 'sort_order' => 10],
        ];

        foreach ($types as $type) {
            AccountType::updateOrCreate(
                ['code' => $type['code']],
                $type
            );
        }
    }
}
