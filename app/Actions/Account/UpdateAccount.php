<?php

namespace App\Actions\Account;

use App\Actions\Action;
use App\Models\Account;
use Illuminate\Support\Facades\DB;

class UpdateAccount
{
    use Action;

    public function handle(array $data, Account $account): Account
    {
        return DB::transaction(function () use ($data, $account) {
            $account->update([
                'account_type_id' => $data['account_type_id'] ?? $account->account_type_id,
                'parent_id' => $data['parent_id'] ?? $account->parent_id,
                'name' => $data['name'] ?? $account->name,
                'description' => $data['description'] ?? $account->description,
                'normal_balance' => $data['normal_balance'] ?? $account->normal_balance,
                'account_category' => $data['account_category'] ?? $account->account_category,
                'cash_flow_section' => array_key_exists('cash_flow_section', $data)
                    ? $data['cash_flow_section']
                    : $account->cash_flow_section,
                'is_cash_account' => $data['is_cash_account'] ?? $account->is_cash_account,
                'is_posting_account' => $data['is_posting_account'] ?? $account->is_posting_account,
                'is_active' => $data['is_active'] ?? $account->is_active,
                'allow_manual_posting' => $data['allow_manual_posting'] ?? $account->allow_manual_posting,
                'currency_id' => $data['currency_id'] ?? $account->currency_id,
                'updated_by' => $data['updated_by'] ?? $account->updated_by,
            ]);

            return $account->fresh();
        });
    }

    public function rules(): array
    {
        return [
            'account_type_id' => ['sometimes', 'exists:account_types,id'],
            'parent_id' => ['nullable', 'integer'],
            'name' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'normal_balance' => ['sometimes', 'string', 'in:debit,credit'],
            'account_category' => ['nullable', 'string', 'in:asset,liability,equity,revenue,expense'],
            'cash_flow_section' => ['nullable', 'string', 'in:operating,investing,financing'],
            'is_cash_account' => ['sometimes', 'boolean'],
            'is_posting_account' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'allow_manual_posting' => ['sometimes', 'boolean'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
        ];
    }
}
