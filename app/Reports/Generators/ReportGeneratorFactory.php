<?php

namespace App\Reports\Generators;

use App\Models\Central\Report;
use App\Reports\Contracts\ReportGenerator;
use App\Reports\Enums\ReportFormat;

class ReportGeneratorFactory
{
    public function make(Report $report): ReportGenerator
    {
        $class = match ($report->format) {
            ReportFormat::Screen => ScreenReportGenerator::class,
            ReportFormat::Pdf => PdfReportGenerator::class,
            ReportFormat::Excel => ExcelReportGenerator::class,
        };

        return app()->make($class, ['report' => $report]);
    }
}
