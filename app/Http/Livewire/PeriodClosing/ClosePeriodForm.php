<?php

namespace App\Http\Livewire\PeriodClosing;

use App\Actions\PeriodClosing\ClosePeriod;
use App\Actions\PeriodClosing\ReopenPeriod;
use App\Models\AccountingPeriod;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ClosePeriodForm extends Component
{
    public int $periodId;

    public string $reason = '';

    public bool $showForm = false;

    public function mount(int $periodId): void
    {
        $this->periodId = $periodId;
    }

    protected array $rules = [
        'reason' => ['nullable', 'string', 'max:500'],
    ];

    public function toggleForm(): void
    {
        $this->showForm = ! $this->showForm;
        if (! $this->showForm) {
            $this->reason = '';
        }
    }

    public function closePeriod(ClosePeriod $closePeriod): void
    {
        $period = AccountingPeriod::findOrFail($this->periodId);
        $user = Auth::user();

        try {
            $closePeriod->run($period, $user, $this->reason ?: null);
            $this->showForm = false;
            $this->reason = '';
            $this->dispatch('period-updated');
            session()->flash('success', 'Period closed successfully. Closing entry generated.');
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function reopenPeriod(ReopenPeriod $reopenPeriod): void
    {
        $this->validate();

        $period = AccountingPeriod::findOrFail($this->periodId);
        $user = Auth::user();

        try {
            $reopenPeriod->run($period, $user, $this->reason);
            $this->showForm = false;
            $this->reason = '';
            $this->dispatch('period-updated');
            session()->flash('success', 'Period reopened successfully.');
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.period-closing.close-period-form');
    }
}
