<?php

namespace App\Http\Livewire\Journal;

use App\Actions\Organization\SwitchOrganization;
use App\Models\Journal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class JournalList extends Component
{
    use WithPagination;

    public string $search = '';

    #[On('journal-created')]
    public function refresh(): void {}

    #[Computed]
    public function journals(): LengthAwarePaginator
    {
        $organization = SwitchOrganization::current(Auth::user());

        if (! $organization) {
            return collect()->paginate(1);
        }

        return Journal::forOrganization($organization)
            ->when($this->search, fn ($q) => $q->where('name', 'ilike', "%{$this->search}%")
                ->orWhere('code', 'ilike', "%{$this->search}%"))
            ->orderBy('code')
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.journal.journal-list');
    }
}
