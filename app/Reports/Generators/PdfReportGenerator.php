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
        $absolutePath = Storage::disk('reports')->path($storagePath);

        @mkdir(dirname($absolutePath), recursive: true);

        $tenantId = $this->report->tenant_id;
        $settings = $tenantId ? $this->settingsService->getSettings($tenantId) : null;

        Pdf::view('reports.pdf', [
            'report' => $this->report,
            'rows' => [],
            'generatedAt' => now()->toDateTimeString(),
            'headerText' => $settings?->reportPdfHeaderText,
            'footerText' => $settings?->reportPdfFooterText,
        ])
            ->format('a4')
            ->save($absolutePath);

        return new ReportResultData(filePath: $storagePath);
    }
}
