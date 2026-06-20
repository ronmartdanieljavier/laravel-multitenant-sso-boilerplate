<?php

namespace App\Reports\Generators;

use App\Models\Central\Report;
use App\Reports\Contracts\ReportGenerator;
use App\Reports\Enums\ReportFormat;
use App\Reports\Services\ReportFileService;

class ReportGeneratorFactory
{
    public function __construct(private readonly ReportFileService $fileService) {}

    public function make(Report $report): ReportGenerator
    {
        return match ($report->format) {
            ReportFormat::Screen => new ScreenReportGenerator($report),
            ReportFormat::Pdf => new PdfReportGenerator($report, $this->fileService),
            ReportFormat::Excel => new ExcelReportGenerator($report, $this->fileService),
        };
    }
}
