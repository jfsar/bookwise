<?php

namespace App\Http\Livewire\FiscalYear;

use App\Actions\Organization\SwitchOrganization;
use App\Models\FiscalYear;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class FiscalYearList extends Component
{
    use WithPagination;

    protected $listeners = ['fiscal-year-created' => '$refresh'];

    public function fiscalYears(): LengthAwarePaginator
    {
        $organization = SwitchOrganization::current(Auth::user());

        if (! $organization) {
            return collect()->paginate(1);
        }

        return FiscalYear::forOrganization($organization)
            ->with(['periods'])
            ->orderByDesc('start_date')
            ->paginate(15);
    }

    public function periods(FiscalYear $fiscalYear): Collection
    {
        return $fiscalYear->periods()->orderBy('period_number')->get();
    }

    public function render()
    {
        return view('livewire.fiscal-year.fiscal-year-list');
    }
}
