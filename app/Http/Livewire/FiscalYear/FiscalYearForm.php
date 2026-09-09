<?php

namespace App\Http\Livewire\FiscalYear;

use App\Actions\FiscalYear\CreateFiscalYear;
use App\Actions\Organization\SwitchOrganization;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class FiscalYearForm extends Component
{
    public string $name = '';

    public string $start_date = '';

    public string $end_date = '';

    public bool $showForm = false;

    protected array $rules = [
        'name' => ['required', 'string', 'max:100'],
        'start_date' => ['required', 'date'],
        'end_date' => ['required', 'date', 'after_or_equal:start_date'],
    ];

    public function toggleForm(): void
    {
        $this->showForm = ! $this->showForm;
        if (! $this->showForm) {
            $this->resetForm();
        }
    }

    public function createFiscalYear(CreateFiscalYear $createFiscalYear): void
    {
        $this->validate();

        $organization = SwitchOrganization::current(Auth::user());
        $user = Auth::user();

        if (! $organization) {
            session()->flash('error', 'No organization selected.');

            return;
        }

        $createFiscalYear->run([
            'name' => $this->name,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
        ], $organization, $user);

        $this->resetForm();
        $this->showForm = false;
        $this->dispatch('fiscal-year-created');
        session()->flash('success', 'Fiscal year created with accounting periods.');
    }

    protected function resetForm(): void
    {
        $this->name = '';
        $this->start_date = '';
        $this->end_date = '';
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.fiscal-year.fiscal-year-form');
    }
}
