<?php

namespace App\Http\Livewire\Accounts;

use App\Actions\Organization\SwitchOrganization;
use App\Models\Account;
use App\Models\AccountType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class AccountList extends Component
{
    use WithPagination;

    public string $search = '';

    public ?string $filterType = null;

    public ?string $filterStatus = null;

    public bool $showInactive = false;

    protected $listeners = ['account-created' => '$refresh', 'account-updated' => '$refresh'];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterType(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function toggleInactive(): void
    {
        $this->showInactive = ! $this->showInactive;
        $this->resetPage();
    }

    public function accounts(): LengthAwarePaginator
    {
        $organization = SwitchOrganization::current(Auth::user());

        if (! $organization) {
            return collect()->paginate(1);
        }

        $query = Account::forOrganization($organization)
            ->with(['accountType', 'parent'])
            ->orderBy('code');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('code', 'ilike', "%{$this->search}%")
                    ->orWhere('name', 'ilike', "%{$this->search}%");
            });
        }

        if ($this->filterType) {
            $query->where('account_type_id', $this->filterType);
        }

        if ($this->filterStatus === 'active') {
            $query->active();
        } elseif ($this->filterStatus === 'inactive') {
            $query->where('is_active', false);
        } elseif (! $this->showInactive) {
            $query->active();
        }

        return $query->paginate(25);
    }

    public function accountTypes(): Collection
    {
        return AccountType::orderBy('sort_order')->get();
    }

    public function render()
    {
        return view('livewire.accounts.account-list');
    }
}
