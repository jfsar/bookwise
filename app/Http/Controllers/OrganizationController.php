<?php

namespace App\Http\Controllers;

use App\Actions\Organization\CreateOrganization;
use App\Actions\Organization\SwitchOrganization;
use App\Actions\Organization\UpdateOrganization;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\Currency;
use App\Models\Organization;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $organization = SwitchOrganization::current($request->user());

        return Inertia::render('Settings/Organization', [
            'organization' => $organization ? $this->serialize($organization) : null,
            'currencies' => Currency::active()
                ->orderBy('code')
                ->get()
                ->values()
                ->map(fn ($currency) => [
                    'id' => $currency->id,
                    'code' => $currency->code,
                    'name' => $currency->name,
                ]),
            'timezones' => collect(DateTimeZone::listIdentifiers())
                ->map(fn (string $identifier) => ['value' => $identifier, 'label' => $identifier])
                ->values(),
        ]);
    }

    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        $user = $request->user();

        (new CreateOrganization)->handle($request->validated(), $user);

        return back()->with('success', 'Organization created successfully.');
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        (new UpdateOrganization)->handle($organization, $request->validated(), $request->user());

        return back()->with('success', 'Organization settings updated.');
    }

    public function switch(Request $request, Organization $organization): RedirectResponse
    {
        (new SwitchOrganization)->handle($request->user(), $organization);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Organization $organization): array
    {
        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'display_name' => $organization->display_name,
            'code' => $organization->code,
            'registration_number' => $organization->registration_number,
            'tax_number' => $organization->tax_number,
            'email' => $organization->email,
            'phone' => $organization->phone,
            'address_line_1' => $organization->address_line_1,
            'address_line_2' => $organization->address_line_2,
            'city' => $organization->city,
            'state' => $organization->state,
            'postal_code' => $organization->postal_code,
            'country_code' => $organization->country_code,
            'base_currency_id' => $organization->base_currency_id,
            'base_currency' => $organization->baseCurrency ? ['id' => $organization->baseCurrency->id, 'code' => $organization->baseCurrency->code] : null,
            'timezone' => $organization->timezone,
            'fiscal_year_start_month' => $organization->fiscal_year_start_month,
            'date_format' => $organization->date_format,
            'decimal_places' => $organization->decimal_places,
        ];
    }
}
