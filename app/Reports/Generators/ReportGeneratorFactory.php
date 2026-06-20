<?php

namespace App\Reports\Generators;

use App\Models\Central\Report;
use App\Reports\Contracts\ReportGenerator;
use App\Reports\Enums\ReportFormat;

class ReportGeneratorFactory
{
    public function make(Report $report): ReportGenerator
    {
        return match ($report->format) {
            ReportFormat::Screen => new ScreenReportGenerator($report),
            ReportFormat::Pdf => new PdfReportGenerator($report),
            ReportFormat::Excel => new ExcelReportGenerator($report),
        };
    }
}
