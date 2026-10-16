<?php

namespace App\Jobs;

use App\Contracts\OrganizationScopedJob;
use App\Enums\ReportExportStatus;
use App\Enums\ReportType;
use App\Exceptions\ReportExportFailedException;
use App\Models\ReportExport;
use App\Notifications\ReportExportReady;
use App\Reports\ReportCsvExporter;
use App\Reports\ReportFactory;
use App\Services\OperationalMetrics;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ExportReportJob implements OrganizationScopedJob, ShouldQueue
{
    use Queueable;

    /**
     * Transient failures (storage/network) are retried with backoff instead of
     * permanently failing a user's export on a momentary blip (§0.25). The
     * schedule is encoded here so behaviour does not depend on the worker flag.
     */
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public $timeout = 300;

    public function __construct(public int $reportExportId) {}

    /**
     * The export may already be gone by the time a failure is reported, so the
     * organization is read defensively rather than assumed.
     */
    public function organizationId(): ?int
    {
        return ReportExport::find($this->reportExportId)?->organization_id;
    }

    public function handle(): void
    {
        $export = ReportExport::find($this->reportExportId);

        if (! $export) {
            return;
        }

        $export->update([
            'status' => ReportExportStatus::Generating,
            'started_at' => now(),
        ]);

        try {
            $report = ReportFactory::make(
                $export->report_type,
                $export->organization,
                $export->filters
            );

            $data = app(OperationalMetrics::class)->measure(
                'report_generation_duration',
                fn (): array => $report->build(),
                ['report_type' => $export->report_type->value, 'source' => 'export'],
            );

            if (! $report->hasRecords($data)) {
                throw new ReportExportFailedException('There are no records to export for the selected report and filters.');
            }

            $csv = $this->render($export->report_type, $data);
            $fileName = str_replace(' ', '-', strtolower($export->report_type->label())).'-'.now()->format('Ymd-His').'.csv';
            $path = 'reports/'.$export->organization_id.'/'.$export->id.'/'.$fileName;

            if (! Storage::disk('s3')->put($path, $csv)) {
                throw new \RuntimeException("Unable to write the report export file to [{$path}].");
            }

            $export->update([
                'status' => ReportExportStatus::Completed,
                'file_name' => $fileName,
                'file_path' => $path,
                'file_size' => strlen($csv),
                'completed_at' => now(),
            ]);

            $export->notify(new ReportExportReady($export));
        } catch (ReportExportFailedException $e) {
            // Permanent: retrying cannot succeed, so fail the export now.
            report($e);
            $this->markFailed($export, $e);
        } catch (Throwable $e) {
            // Transient: rethrow so the queue retries with backoff, and only
            // record the terminal failure on the final attempt (which also
            // raises JobFailed so administrators are notified, §7.39).
            if ($this->attempts() >= $this->tries) {
                $this->markFailed($export, $e);
            }

            throw $e;
        }
    }

    private function markFailed(ReportExport $export, Throwable $e): void
    {
        $export->update([
            'status' => ReportExportStatus::Failed,
            'error_message' => $e->getMessage(),
            'completed_at' => now(),
        ]);
    }

    protected function render(ReportType $type, array $data): string
    {
        $result = (new ReportCsvExporter)->export($type, $data);

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $result['headers']);

        foreach ($result['rows'] as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }
}
