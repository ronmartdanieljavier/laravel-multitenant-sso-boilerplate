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

        $successfulReports
            ->groupBy(fn (Report $r) => $r->format->value.'|'.$r->delivery->value)
            ->each(function (Collection $group) use ($batchId): void {
                $format = $group->first()->format;
                $delivery = $group->first()->delivery;

                if ($format === ReportFormat::Pdf && $group->count() > 1) {
                    // TODO: merge PDFs once setasign/fpdi or similar is installed
                }

                if ($delivery === ReportDelivery::Download && $group->count() > 1) {
                    $paths = $group->pluck('file_path')->filter()->all();
                    $zipPath = $this->fileService->zipFiles($paths, "batch_{$batchId}.zip");

                    // Store the ZIP path on the first report so the batch download is retrievable via the API.
                    $group->first()->fresh()?->update(['file_path' => $zipPath]);
                }
            });
    }
}
