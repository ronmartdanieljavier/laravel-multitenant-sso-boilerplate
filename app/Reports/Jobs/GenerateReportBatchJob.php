<?php

namespace App\Reports\Jobs;

use App\Models\Central\Report;
use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Services\ReportFileService;
use Illuminate\Bus\Batch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;

class GenerateReportBatchJob
{
    public function __construct(
        private readonly ReportFileService $fileService,
    ) {}

    /**
     * @param  Collection<int, Report>  $reports
     */
    public function dispatch(Collection $reports, string $batchId): Batch
    {
        $jobs = $reports->map(fn (Report $report) => new GenerateReportJob($report))->all();

        return Bus::batch($jobs)
            ->then(function (Batch $batch) use ($reports, $batchId) {
                $this->handleBatchCompletion($reports, $batchId);
            })
            ->name("Batch Report: {$batchId}")
            ->onQueue('reports')
            ->dispatch();
    }

    /**
     * @param  Collection<int, Report>  $reports
     */
    private function handleBatchCompletion(Collection $reports, string $batchId): void
    {
        $successfulReports = $reports->filter(
            fn (Report $r) => $r->fresh()?->file_path !== null
        );

        if ($successfulReports->isEmpty()) {
            return;
        }

        $firstReport = $successfulReports->first();

        if ($firstReport->format === ReportFormat::Pdf && $successfulReports->count() > 1) {
            // TODO: merge PDFs once barryvdh/laravel-dompdf or similar is installed
        }

        if ($firstReport->delivery === ReportDelivery::Download && $successfulReports->count() > 1) {
            $paths = $successfulReports->pluck('file_path')->filter()->all();
            $this->fileService->zipFiles($paths, "batch_{$batchId}.zip");
        }
    }
}
