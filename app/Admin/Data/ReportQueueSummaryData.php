<?php

namespace App\Admin\Data;

use Spatie\LaravelData\Data;

class ReportQueueSummaryData extends Data
{
    /**
     * @param  array<int, ReportQueueTenantData>  $byTenant
     */
    public function __construct(
        public int $pending,
        public int $processing,
        public int $failed,
        public array $byTenant,
    ) {}
}
