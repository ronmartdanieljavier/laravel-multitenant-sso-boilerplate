<?php

namespace App\Reports\Generators;

use App\Models\Central\Report;
use App\Reports\Contracts\ReportGenerator;
use App\Reports\Data\ReportResultData;
use App\Reports\Exports\ReportExport;
use App\Reports\Services\ReportFileService;
use Maatwebsite\Excel\Facades\Excel;

class ExcelReportGenerator implements ReportGenerator
{
    public function __construct(
        private readonly Report $report,
    ) {}

    public function generate(): ReportResultData
    {
        $filename = "report_{$this->report->id}.xlsx";
        $storagePath = ReportFileService::prefix($this->report).'/'.$filename;

        Excel::store(new ReportExport($this->report, rows: []), $storagePath, 'reports');

        return new ReportResultData(filePath: $storagePath);
    }
}
