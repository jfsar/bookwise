<?php

namespace App\Http\Controllers;

use App\Actions\FiscalYear\CreateFiscalYear;
use App\Actions\Organization\SwitchOrganization;
use App\Http\Requests\StoreFiscalYearRequest;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FiscalYearController extends Controller
{
    public function index(Request $request): Response
    {
        $organization = SwitchOrganization::current($request->user());

        $paginator = $organization
            ? FiscalYear::forOrganization($organization)
                ->with(['periods'])
                ->orderByDesc('start_date')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (FiscalYear $fiscalYear) => $this->serialize($fiscalYear))
                ->toArray()
            : null;

        return Inertia::render('FiscalYears/Index', [
            'fiscalYears' => $paginator,
        ]);
    }

    public function store(StoreFiscalYearRequest $request): RedirectResponse
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        (new CreateFiscalYear)->run($request->validated(), $organization, $user);

        return back()->with('success', 'Fiscal year created with accounting periods.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(FiscalYear $fiscalYear): array
    {
        return [
            'id' => $fiscalYear->id,
            'name' => $fiscalYear->name,
            'status' => $fiscalYear->status->value,
            'start_date' => $fiscalYear->start_date?->format('M d, Y'),
            'end_date' => $fiscalYear->end_date?->format('M d, Y'),
            'periods' => $fiscalYear->periods
                ->sortBy('period_number')
                ->values()
                ->map(fn (AccountingPeriod $period) => [
                    'id' => $period->id,
                    'name' => $period->name,
                    'status' => $period->status->value,
                    'is_open' => $period->isOpen(),
                    'start_date' => $period->start_date?->format('M d'),
                    'end_date' => $period->end_date?->format('M d'),
                ]),
        ];
    }
}
