<?php

namespace App\Http\Controllers;

use App\Actions\Organization\SwitchOrganization;
use App\Enums\ReportExportStatus;
use App\Enums\ReportType;
use App\Http\Requests\ReportRequest;
use App\Jobs\ExportReportJob;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\ReportExport;
use App\Reports\ReportFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function generalLedger(ReportRequest $request): Response
    {
        return $this->show(ReportType::GeneralLedger, $request);
    }

    public function trialBalance(ReportRequest $request): Response
    {
        return $this->show(ReportType::TrialBalance, $request);
    }

    public function incomeStatement(ReportRequest $request): Response
    {
        return $this->show(ReportType::IncomeStatement, $request);
    }

    public function balanceSheet(ReportRequest $request): Response
    {
        return $this->show(ReportType::BalanceSheet, $request);
    }

    public function cashFlow(ReportRequest $request): Response
    {
        return $this->show(ReportType::CashFlow, $request);
    }

    public function arAging(ReportRequest $request): Response
    {
        return $this->show(ReportType::ArAging, $request);
    }

    public function exports(Request $request): JsonResponse
    {
        $this->authorize('viewReports', ReportExport::class);

        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return response()->json(['recentExports' => []]);
        }

        return response()->json([
            'recentExports' => $this->recentExports($organization, $user->id),
        ]);
    }

    public function store(ReportRequest $request): RedirectResponse
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        $this->authorize('viewReports', ReportExport::class);

        $type = ReportType::from($request->validated('report_type'));

        $report = ReportFactory::make($type, $organization, $request->filters());

        if (! $report->hasRecords($report->build())) {
            return back()->with('error', 'There are no records for the selected report and filters.');
        }

        $export = ReportExport::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'report_type' => $type,
            'filters' => $request->filters(),
            'request_hash' => $this->requestHash($type, $request->filters()),
            'status' => ReportExportStatus::Queued,
        ]);

        ExportReportJob::dispatch($export->id);

        return back()->with('success', 'Report export queued. You will be notified when it is ready.');
    }

    public function download(Request $request, ReportExport $reportExport): StreamedResponse
    {
        $this->authorize('download', $reportExport);

        if ($reportExport->status !== ReportExportStatus::Completed || ! $reportExport->file_path) {
            abort(404);
        }

        if (! Storage::disk('s3')->exists($reportExport->file_path)) {
            abort(404);
        }

        return Storage::disk('s3')->download($reportExport->file_path, $reportExport->file_name);
    }

    protected function show(ReportType $type, ReportRequest $request): Response
    {
        $this->authorize('viewReports', ReportExport::class);

        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return $this->render($type, $request, null, null);
        }

        $report = ReportFactory::make($type, $organization, $request->filters());
        $data = $report->build();

        return $this->render($type, $request, $organization, $data, $report->hasRecords($data));
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    protected function render(
        ReportType $type,
        ReportRequest $request,
        ?Organization $organization,
        ?array $data,
        bool $hasRecords = false,
    ): Response {
        return Inertia::render($this->page($type), [
            'reportType' => $type->value,
            'hasRecords' => $hasRecords,
            'currency' => $organization?->baseCurrency->code,
            'data' => $data,
            'filters' => $request->filters(),
            'accounts' => $organization
                ? $this->accounts($organization)
                : [],
            'journals' => $organization
                ? $this->journals($organization)
                : [],
            'fiscalYears' => $organization
                ? $this->fiscalYears($organization)
                : [],
            'accountingPeriods' => $organization
                ? $this->accountingPeriods($organization)
                : [],
            'recentExports' => $organization
                ? $this->recentExports($organization, $request->user()->id)
                : [],
        ]);
    }

    protected function page(ReportType $type): string
    {
        return match ($type) {
            ReportType::GeneralLedger => 'Reports/GeneralLedger',
            ReportType::TrialBalance => 'Reports/TrialBalance',
            ReportType::IncomeStatement => 'Reports/IncomeStatement',
            ReportType::BalanceSheet => 'Reports/BalanceSheet',
            ReportType::CashFlow => 'Reports/CashFlow',
            ReportType::ArAging => 'Reports/ArAging',
        };
    }

    /**
     * @return array<int, array{id: int, code: string, name: string}>
     */
    protected function accounts(Organization $organization): array
    {
        return Account::forOrganization($organization)
            ->active()
            ->posting()
            ->orderBy('code')
            ->get()
            ->values()
            ->map(fn ($account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, code: string, name: string}>
     */
    protected function journals(Organization $organization): array
    {
        return Journal::forOrganization($organization)
            ->active()
            ->orderBy('code')
            ->get()
            ->values()
            ->map(fn ($journal) => [
                'id' => $journal->id,
                'code' => $journal->code,
                'name' => $journal->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    protected function fiscalYears(Organization $organization): array
    {
        return FiscalYear::forOrganization($organization)
            ->open()
            ->orderByDesc('start_date')
            ->get()
            ->values()
            ->map(fn ($fiscalYear) => [
                'id' => $fiscalYear->id,
                'name' => $fiscalYear->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, fiscal_year_id: int}>
     */
    protected function accountingPeriods(Organization $organization): array
    {
        return AccountingPeriod::forOrganization($organization)
            ->open()
            ->orderBy('start_date')
            ->get()
            ->values()
            ->map(fn ($period) => [
                'id' => $period->id,
                'name' => $period->name,
                'fiscal_year_id' => $period->fiscal_year_id,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function recentExports(Organization $organization, int $userId): array
    {
        return ReportExport::forOrganization($organization)
            ->where('user_id', $userId)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (ReportExport $export) => [
                'id' => $export->id,
                'report_type' => $export->report_type->value,
                'status' => $export->status->value,
                'file_name' => $export->file_name,
                'created_at' => $export->created_at?->toIso8601String(),
                'download_url' => $export->status === ReportExportStatus::Completed
                    ? route('reports.download', $export)
                    : null,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function requestHash(ReportType $type, array $filters): string
    {
        return hash('sha256', json_encode(['type' => $type->value, 'filters' => $filters]));
    }
}
