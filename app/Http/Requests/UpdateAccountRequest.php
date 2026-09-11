<?php

namespace App\Http\Requests;

use App\Actions\Organization\SwitchOrganization;
use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'account_type_id' => ['required', 'exists:account_types,id'],
            'parent_id' => ['nullable', 'integer'],
            'normal_balance' => ['required', 'string', 'in:debit,credit'],
            'is_posting_account' => ['boolean'],
            'is_active' => ['boolean'],
            'allow_manual_posting' => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('parent_id') === $this->route('account')->id) {
                $validator->errors()->add('parent_id', 'An account cannot be its own parent.');

                return;
            }

            if (! $this->filled('parent_id')) {
                return;
            }

            $organization = SwitchOrganization::current($this->user());

            $parentExists = $organization
                ? Account::forOrganization($organization)->where('id', $this->input('parent_id'))->exists()
                : false;

            if (! $parentExists) {
                $validator->errors()->add('parent_id', 'The selected parent account does not belong to this organization.');
            }
        });
    }
}
