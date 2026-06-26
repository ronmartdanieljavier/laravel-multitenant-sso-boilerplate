<?php

namespace App\Tenant\Data;

use App\Data\Repositories\Central\DocumentRepositoryData;
use App\Data\Repositories\Central\ReportRepositoryData;
use App\Data\Repositories\Central\TenantErrorLogRepositoryData;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class TenantDashboardData extends Data
{
    /**
     * @param  Collection<int, ReportRepositoryData>  $recentReports
     * @param  Collection<int, TenantErrorLogRepositoryData>  $recentErrors
     * @param  Collection<int, DocumentRepositoryData>  $recentDocuments
     */
    public function __construct(
        public readonly TenantDashboardStatsData $stats,
        public readonly Collection $recentReports,
        public readonly Collection $recentErrors,
        public readonly Collection $recentDocuments,
    ) {}
}
