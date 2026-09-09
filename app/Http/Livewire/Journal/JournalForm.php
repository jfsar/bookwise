<?php

namespace App\Http\Livewire\Journal;

use App\Actions\Journal\CreateJournal;
use App\Actions\Organization\SwitchOrganization;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class JournalForm extends Component
{
    public string $code = '';

    public string $name = '';

    public string $description = '';

    public bool $showForm = false;

    protected array $rules = [
        'code' => ['required', 'string', 'max:20'],
        'name' => ['required', 'string', 'max:100'],
        'description' => ['nullable', 'string', 'max:500'],
    ];

    public function toggleForm(): void
    {
        $this->showForm = ! $this->showForm;
        if (! $this->showForm) {
            $this->resetForm();
        }
    }

    public function createJournal(CreateJournal $createJournal): void
    {
        $this->validate();

        $organization = SwitchOrganization::current(Auth::user());
        $user = Auth::user();

        if (! $organization) {
            session()->flash('error', 'No organization selected.');

            return;
        }

        $createJournal->run([
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
        ], $organization, $user);

        $this->resetForm();
        $this->showForm = false;
        $this->dispatch('journal-created');
        session()->flash('success', 'Journal created successfully.');
    }

    protected function resetForm(): void
    {
        $this->code = '';
        $this->name = '';
        $this->description = '';
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.journal.journal-form');
    }
}
