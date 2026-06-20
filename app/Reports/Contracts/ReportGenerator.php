<?php

namespace App\Reports\Contracts;

use App\Reports\Data\ReportResultData;

interface ReportGenerator
{
    public function generate(): ReportResultData;
}
