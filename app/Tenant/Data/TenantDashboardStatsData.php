<?php

namespace App\Tenant\Data;

use Spatie\LaravelData\Data;

class TenantDashboardStatsData extends Data
{
    public function __construct(
        public readonly int $pendingReports,
        public readonly int $processingReports,
        public readonly int $failedReports,
        public readonly int $successReportsThisMonth,
        public readonly int $unresolvedErrors,
        public readonly int $criticalErrors,
        public readonly int $totalDocuments,
    ) {}
}
