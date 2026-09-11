<?php

namespace App\Http\Controllers;

use App\Actions\Organization\SwitchOrganization;
use App\Actions\PeriodClosing\ClosePeriod;
use App\Actions\PeriodClosing\ReopenPeriod;
use App\Http\Requests\ClosePeriodRequest;
use App\Http\Requests\ReopenPeriodRequest;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PeriodClosingController extends Controller
{
    public function index(Request $request): Response
    {
        $organization = SwitchOrganization::current($request->user());

        if (! $organization) {
            return Inertia::render('PeriodClosing/Index', [
                'fiscalYears' => [],
                'periods' => [],
                'filters' => $request->only(['fiscal_year_id']),
            ]);
        }

        $fiscalYears = FiscalYear::forOrganization($organization)
            ->orderByDesc('start_date')
            ->get()
            ->values()
            ->map(fn ($fiscalYear) => [
                'id' => $fiscalYear->id,
                'name' => $fiscalYear->name,
            ]);

        $periods = AccountingPeriod::forOrganization($organization)
            ->with('fiscalYear')
            ->when($request->filled('fiscal_year_id'), function ($query) use ($request) {
                $query->where('fiscal_year_id', $request->input('fiscal_year_id'));
            })
            ->orderByDesc('start_date')
            ->get()
            ->values()
            ->map(fn (AccountingPeriod $period) => [
                'id' => $period->id,
                'name' => $period->name,
                'status' => $period->status->value,
                'is_open' => $period->isOpen(),
                'start_date' => $period->start_date?->format('M d'),
                'end_date' => $period->end_date?->format('M d, Y'),
                'fiscal_year' => $period->fiscalYear ? ['id' => $period->fiscalYear->id, 'name' => $period->fiscalYear->name] : null,
            ]);

        return Inertia::render('PeriodClosing/Index', [
            'fiscalYears' => $fiscalYears,
            'periods' => $periods,
            'filters' => $request->only(['fiscal_year_id']),
        ]);
    }

    public function close(ClosePeriodRequest $request, AccountingPeriod $accountingPeriod): RedirectResponse
    {
        try {
            (new ClosePeriod)->handle($accountingPeriod, $request->user(), $request->input('reason') ?: null);

            return back()->with('success', 'Period closed successfully. Closing entry generated.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reopen(ReopenPeriodRequest $request, AccountingPeriod $accountingPeriod): RedirectResponse
    {
        try {
            (new ReopenPeriod)->handle($accountingPeriod, $request->user(), $request->input('reason'));

            return back()->with('success', 'Period reopened successfully.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
