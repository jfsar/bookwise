<?php

namespace App\Http\Livewire\Accounts;

use App\Actions\Account\CreateAccount;
use App\Actions\Account\UpdateAccount;
use App\Actions\Organization\SwitchOrganization;
use App\Models\Account;
use App\Models\AccountType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AccountForm extends Component
{
    public ?int $accountId = null;

    public bool $showForm = false;

    public string $code = '';

    public string $name = '';

    public ?string $description = null;

    public ?int $account_type_id = null;

    public ?int $parent_id = null;

    public string $normal_balance = 'debit';

    public bool $is_posting_account = true;

    public bool $is_active = true;

    public bool $allow_manual_posting = true;

    protected array $rules = [
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

    public function mount(?int $accountId = null): void
    {
        if ($accountId) {
            $account = Account::findOrFail($accountId);
            $this->accountId = $account->id;
            $this->code = $account->code;
            $this->name = $account->name;
            $this->description = $account->description;
            $this->account_type_id = $account->account_type_id;
            $this->parent_id = $account->parent_id;
            $this->normal_balance = $account->normal_balance->value;
            $this->is_posting_account = $account->is_posting_account;
            $this->is_active = $account->is_active;
            $this->allow_manual_posting = $account->allow_manual_posting;
            $this->showForm = true;
        }
    }

    #[Computed]
    public function accountTypes(): Collection
    {
        return AccountType::orderBy('sort_order')->get();
    }

    #[Computed]
    public function parentAccounts(): Collection
    {
        $organization = SwitchOrganization::current(Auth::user());

        if (! $organization) {
            return new Collection;
        }

        return Account::forOrganization($organization)
            ->active()
            ->where('id', '!=', $this->accountId)
            ->orderBy('code')
            ->get();
    }

    public function toggleForm(): void
    {
        $this->showForm = ! $this->showForm;
        if (! $this->showForm) {
            $this->resetForm();
        }
    }

    public function saveAccount(CreateAccount $createAccount, UpdateAccount $updateAccount): void
    {
        $this->validate();

        $organization = SwitchOrganization::current(Auth::user());
        $user = Auth::user();

        if (! $organization) {
            session()->flash('error', 'No organization selected.');

            return;
        }

        // Validate parent belongs to same org
        if ($this->parent_id) {
            $parent = Account::where('organization_id', $organization->id)
                ->where('id', $this->parent_id)
                ->first();

            if (! $parent) {
                $this->addError('parent_id', 'The selected parent account does not belong to this organization.');

                return;
            }

            // Prevent self-parenting
            if ($this->parent_id === $this->accountId) {
                $this->addError('parent_id', 'An account cannot be its own parent.');

                return;
            }
        }

        $data = [
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'account_type_id' => $this->account_type_id,
            'parent_id' => $this->parent_id,
            'normal_balance' => $this->normal_balance,
            'is_posting_account' => $this->is_posting_account,
            'is_active' => $this->is_active,
            'allow_manual_posting' => $this->allow_manual_posting,
        ];

        if ($this->accountId) {
            $account = Account::findOrFail($this->accountId);
            $data['updated_by'] = $user->id;
            $updateAccount->run($data, $account);
            session()->flash('success', 'Account updated successfully.');
        } else {
            $data['organization_id'] = $organization->id;
            $createAccount->run($data, $organization, $user);
            session()->flash('success', 'Account created successfully.');
        }

        $this->resetForm();
        $this->showForm = false;
        $this->dispatch('account-created');
    }

    protected function resetForm(): void
    {
        $this->accountId = null;
        $this->code = '';
        $this->name = '';
        $this->description = null;
        $this->account_type_id = null;
        $this->parent_id = null;
        $this->normal_balance = 'debit';
        $this->is_posting_account = true;
        $this->is_active = true;
        $this->allow_manual_posting = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.accounts.account-form');
    }
}
