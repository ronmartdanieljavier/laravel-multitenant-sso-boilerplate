<?php

namespace App\Reports\Generators;

use App\Models\Central\Report;
use App\Reports\Contracts\ReportGenerator;
use App\Reports\Data\ReportResultData;

class ScreenReportGenerator implements ReportGenerator
{
    public function __construct(private readonly Report $report) {}

    public function generate(): ReportResultData
    {
        // TODO: implement actual data query based on $this->report->type
        $data = [
            'type' => $this->report->type,
            'parameters' => $this->report->parameters,
            'generated_at' => now()->toIso8601String(),
            'rows' => [],
        ];

        return new ReportResultData(data: $data);
    }
}
