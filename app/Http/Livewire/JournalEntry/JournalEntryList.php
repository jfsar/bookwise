<?php

namespace App\Http\Livewire\JournalEntry;

use App\Actions\Organization\SwitchOrganization;
use App\Models\JournalEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class JournalEntryList extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    protected $listeners = ['entry-created' => '$refresh'];

    public function entries(): LengthAwarePaginator
    {
        $organization = SwitchOrganization::current(Auth::user());

        if (! $organization) {
            return collect()->paginate(1);
        }

        return JournalEntry::forOrganization($organization)
            ->with(['journal', 'currency', 'accountingPeriod'])
            ->when($this->search, fn ($q) => $q->where('entry_number', 'ilike', "%{$this->search}%")
                ->orWhere('description', 'ilike', "%{$this->search}%"))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->orderByDesc('entry_date')
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.journal-entry.journal-entry-list');
    }
}
