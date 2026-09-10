<?php

namespace App\Http\Livewire\PeriodClosing;

use App\Actions\Organization\SwitchOrganization;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class PeriodClosingList extends Component
{
    public ?int $selectedFiscalYearId = null;

    #[On('period-updated')]
    public function refresh(): void {}

    #[Computed]
    public function fiscalYears()
    {
        $organization = SwitchOrganization::current(Auth::user());

        return $organization ? FiscalYear::forOrganization($organization)->get() : collect();
    }

    #[Computed]
    public function periods()
    {
        $organization = SwitchOrganization::current(Auth::user());
        if (! $organization) {
            return collect();
        }

        $query = AccountingPeriod::forOrganization($organization)
            ->with(['fiscalYear']);

        if ($this->selectedFiscalYearId) {
            $query->where('fiscal_year_id', $this->selectedFiscalYearId);
        }

        return $query->orderBy('start_date', 'desc')->get();
    }

    public function render()
    {
        return view('livewire.period-closing.period-closing-list');
    }
}
