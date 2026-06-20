<?php

namespace App\Reports\Generators;

use App\Models\Central\Report;
use App\Reports\Contracts\ReportGenerator;
use App\Reports\Data\ReportResultData;
use App\Reports\Exports\ReportExport;
use Maatwebsite\Excel\Facades\Excel;

class ExcelReportGenerator implements ReportGenerator
{
    public function __construct(
        private readonly Report $report,
    ) {}

    public function generate(): ReportResultData
    {
        $filename = "report_{$this->report->id}.xlsx";
        $storagePath = "reports/{$this->report->user_id}/{$filename}";

        Excel::store(new ReportExport($this->report, rows: []), $storagePath);

        return new ReportResultData(filePath: $storagePath);
    }
}
