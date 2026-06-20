<?php

namespace App\Reports\Generators;

use App\Models\Central\Report;
use App\Reports\Contracts\ReportGenerator;
use App\Reports\Data\ReportResultData;
use Spatie\LaravelPdf\Facades\Pdf;

class PdfReportGenerator implements ReportGenerator
{
    public function __construct(
        private readonly Report $report,
    ) {}

    public function generate(): ReportResultData
    {
        $filename = "report_{$this->report->id}.pdf";
        $storagePath = "reports/{$this->report->user_id}/{$filename}";
        $absolutePath = storage_path("app/{$storagePath}");

        @mkdir(dirname($absolutePath), recursive: true);

        Pdf::view('reports.pdf', [
            'report' => $this->report,
            'rows' => [],
            'generatedAt' => now()->toDateTimeString(),
        ])
            ->format('a4')
            ->save($absolutePath);

        return new ReportResultData(filePath: $storagePath);
    }
}
