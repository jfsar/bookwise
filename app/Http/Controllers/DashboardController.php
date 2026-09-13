<?php

namespace App\Http\Controllers;

use App\Actions\Organization\SwitchOrganization;
use App\Dashboard\DashboardMetricsService;
use App\Models\Currency;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $organization = SwitchOrganization::current(request()->user());

        return Inertia::render('Dashboard', [
            'metrics' => $organization
                ? (new DashboardMetricsService)->forOrganization($organization)
                : null,
            'currencies' => Currency::active()
                ->orderBy('code')
                ->get()
                ->values()
                ->map(fn ($currency) => [
                    'id' => $currency->id,
                    'code' => $currency->code,
                    'name' => $currency->name,
                ]),
        ]);
    }
}
