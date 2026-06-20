<?php

namespace App\Reports\Generators;

use App\Models\Central\Report;
use App\Reports\Contracts\ReportGenerator;
use App\Reports\Data\ReportResultData;
use App\Reports\Services\ReportFileService;
use RuntimeException;

class PdfReportGenerator implements ReportGenerator
{
    /** @phpstan-ignore property.onlyWritten */
    private readonly Report $report;

    /** @phpstan-ignore property.onlyWritten */
    private readonly ReportFileService $fileService;

    public function __construct(Report $report, ReportFileService $fileService)
    {
        $this->report = $report;
        $this->fileService = $fileService;
    }

    public function generate(): ReportResultData
    {
        // TODO: install barryvdh/laravel-dompdf or spatie/laravel-pdf
        throw new RuntimeException('PDF generation not yet implemented. Install a PDF package first.');
    }
}
