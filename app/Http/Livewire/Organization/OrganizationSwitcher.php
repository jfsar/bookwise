<?php

namespace App\Http\Livewire\Organization;

use App\Actions\Organization\SwitchOrganization;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class OrganizationSwitcher extends Component
{
    public ?int $currentOrganizationId = null;

    #[On('organization-created')]
    public function refresh(): void {}

    public function mount(): void
    {
        $this->currentOrganizationId = SwitchOrganization::current(Auth::user())?->id;
    }

    #[Computed]
    public function organizations(): Collection
    {
        return Auth::user()->organizations()
            ->wherePivot('membership_status', 'active')
            ->get();
    }

    #[Computed]
    public function currentOrganization(): ?Organization
    {
        return SwitchOrganization::current(Auth::user());
    }

    public function switchTo(int $organizationId): void
    {
        $organization = Organization::findOrFail($organizationId);
        $user = Auth::user();

        (new SwitchOrganization)->handle($user, $organization);

        $this->currentOrganizationId = $organization->id;

        $this->dispatch('organization-switched', $organization->id);
    }

    public function render()
    {
        return view('livewire.organization.organization-switcher');
    }
}
