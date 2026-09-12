<?php

namespace App\Actions\Account;

use App\Actions\Action;
use App\Models\Account;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateAccount
{
    use Action;

    public function handle(array $data, Organization $organization, User $creator): Account
    {
        return DB::transaction(function () use ($data, $organization, $creator) {
            $account = Account::create([
                'organization_id' => $organization->id,
                'account_type_id' => $data['account_type_id'],
                'parent_id' => $data['parent_id'] ?? null,
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'normal_balance' => $data['normal_balance'],
                'account_category' => $data['account_category'] ?? null,
                'cash_flow_section' => $data['cash_flow_section'] ?? null,
                'is_cash_account' => $data['is_cash_account'] ?? false,
                'is_posting_account' => $data['is_posting_account'] ?? false,
                'is_system_account' => false,
                'is_active' => $data['is_active'] ?? true,
                'allow_manual_posting' => $data['allow_manual_posting'] ?? true,
                'currency_id' => $data['currency_id'] ?? null,
                'created_by' => $creator->id,
            ]);

            return $account;
        });
    }

    public function rules(): array
    {
        return [
            'account_type_id' => ['required', 'exists:account_types,id'],
            'parent_id' => ['nullable', 'integer'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'normal_balance' => ['required', 'string', 'in:debit,credit'],
            'account_category' => ['nullable', 'string', 'in:asset,liability,equity,revenue,expense'],
            'cash_flow_section' => ['nullable', 'string', 'in:operating,investing,financing'],
            'is_cash_account' => ['nullable', 'boolean'],
            'is_posting_account' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'allow_manual_posting' => ['nullable', 'boolean'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
        ];
    }
}
