<?php

namespace App\Http\Controllers;

use App\Actions\Organization\CreateOrganization;
use App\Actions\Organization\SwitchOrganization;
use App\Http\Requests\StoreOrganizationRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        $user = $request->user();

        (new CreateOrganization)->run($request->validated(), $user);

        return back()->with('success', 'Organization created successfully.');
    }

    public function switch(Request $request, Organization $organization): RedirectResponse
    {
        (new SwitchOrganization)->handle($request->user(), $organization);

        return back();
    }
}
