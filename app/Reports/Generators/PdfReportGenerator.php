<?php

namespace App\Reports\Generators;

use App\Admin\Services\TenantSettingsService;
use App\Models\Central\Report;
use App\Reports\Contracts\ReportGenerator;
use App\Reports\Data\ReportResultData;
use App\Reports\Services\ReportFileService;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;

class PdfReportGenerator implements ReportGenerator
{
    public function __construct(
        private readonly Report $report,
        private readonly TenantSettingsService $settingsService,
    ) {}

    public function generate(): ReportResultData
    {
        $filename = "report_{$this->report->id}.pdf";
        $storagePath = ReportFileService::prefix($this->report).'/'.$filename;
        $disk = Storage::disk('reports');

        $disk->makeDirectory(dirname($storagePath));

        $tenantId = $this->report->tenant_id;
        $settings = $tenantId ? $this->settingsService->getSettings($tenantId) : null;

        $absolutePath = $disk->path($storagePath);

        Pdf::view('reports.pdf', [
            'report' => $this->report,
            'rows' => [],
            'generatedAt' => now()->toDateTimeString(),
            'headerText' => $settings?->reportPdfHeaderText,
            'footerText' => $settings?->reportPdfFooterText,
        ])
            ->format('a4')
            ->save($absolutePath);

        if (! $disk->exists($storagePath)) {
            throw new \RuntimeException("PDF report was not written to disk at {$storagePath}.");
        }

        return new ReportResultData(filePath: $storagePath);
    }
}
