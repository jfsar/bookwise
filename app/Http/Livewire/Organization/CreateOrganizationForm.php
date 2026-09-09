<?php

namespace App\Http\Livewire\Organization;

use App\Actions\Organization\CreateOrganization;
use App\Actions\Organization\SwitchOrganization;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CreateOrganizationForm extends Component
{
    public string $name = '';

    public ?string $display_name = null;

    public string $code = '';

    public ?string $country_code = 'US';

    public int $base_currency_id = 0;

    public string $timezone = 'UTC';

    public int $fiscal_year_start_month = 1;

    public string $date_format = 'Y-m-d';

    public int $decimal_places = 2;

    public bool $showForm = false;

    protected array $rules = [
        'name' => ['required', 'string', 'max:200'],
        'code' => ['required', 'string', 'max:50'],
        'country_code' => ['required', 'string', 'size:2'],
        'base_currency_id' => ['required', 'integer', 'exists:currencies,id'],
        'timezone' => ['required', 'string', 'max:100'],
        'fiscal_year_start_month' => ['required', 'integer', 'between:1,12'],
    ];

    public function mount(): void
    {
        $defaultCurrency = Currency::where('code', 'USD')->first();
        $this->base_currency_id = $defaultCurrency?->id ?? 0;
    }

    public function currencies(): Collection
    {
        return Currency::active()->get();
    }

    public function toggleForm(): void
    {
        $this->showForm = ! $this->showForm;
        $this->resetValidation();
    }

    public function createOrganization(CreateOrganization $createOrganization): void
    {
        $this->validate();

        $user = Auth::user();
        $organization = $createOrganization->run([
            'name' => $this->name,
            'display_name' => $this->display_name,
            'code' => $this->code,
            'country_code' => $this->country_code,
            'base_currency_id' => $this->base_currency_id,
            'timezone' => $this->timezone,
            'fiscal_year_start_month' => $this->fiscal_year_start_month,
            'date_format' => $this->date_format,
            'decimal_places' => $this->decimal_places,
        ], $user);

        SwitchOrganization::current($user);

        $this->reset();
        $this->showForm = false;

        $this->dispatch('organization-created', $organization->id);
        session()->flash('success', 'Organization created successfully.');
    }

    public function render()
    {
        return view('livewire.organization.create-organization-form');
    }
}
