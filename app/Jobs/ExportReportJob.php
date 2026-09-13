<?php

namespace App\Jobs;

use App\Enums\ReportExportStatus;
use App\Enums\ReportType;
use App\Models\ReportExport;
use App\Notifications\ReportExportReady;
use App\Reports\ReportCsvExporter;
use App\Reports\ReportFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ExportReportJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 300;

    public function __construct(public int $reportExportId) {}

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

            $csv = $this->render($export->report_type, $report->build());
            $fileName = str_replace(' ', '-', strtolower($export->report_type->label())).'-'.now()->format('Ymd-His').'.csv';
            $path = 'reports/'.$export->organization_id.'/'.$export->id.'/'.$fileName;

            Storage::disk('local')->put($path, $csv);

            $export->update([
                'status' => ReportExportStatus::Completed,
                'file_name' => $fileName,
                'file_path' => $path,
                'file_size' => strlen($csv),
                'completed_at' => now(),
            ]);

            $export->notify(new ReportExportReady($export));
        } catch (\Throwable $e) {
            report($e);

            $export->update([
                'status' => ReportExportStatus::Failed,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }
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
