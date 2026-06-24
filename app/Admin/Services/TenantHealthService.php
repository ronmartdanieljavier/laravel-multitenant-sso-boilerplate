<?php

namespace App\Admin\Services;

use App\Admin\Data\TenantHealthData;
use App\Admin\Data\TenantHealthSummaryData;
use App\Data\Repositories\Central\TenantRepositoryData;
use App\Repositories\Central\ReportRepository;
use App\Repositories\Central\TenantRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TenantHealthService
{
    public function __construct(
        protected TenantRepository $tenantRepository,
        protected ReportRepository $reportRepository,
    ) {}

    /**
     * Get a list of tenants with their health data.
     *
     * @return Collection<int, TenantHealthData>
     */
    public function getTenants(): Collection
    {
        $tenants = $this->tenantRepository->allWithMigrationVersions();

        $pendingByTenant = $this->reportRepository->pendingCountByTenant();
        $failedByTenant = $this->reportRepository->failedCountByTenant();
        $lastReportByTenant = $this->reportRepository->lastSuccessAtByTenant();
        $userCountByTenant = $this->tenantRepository->userCountByTenant();

        return $tenants->map(function (TenantRepositoryData $tenant) use (
            $pendingByTenant,
            $failedByTenant,
            $lastReportByTenant,
            $userCountByTenant,
        ) {
            $versions = collect($tenant->migrationVersions);
            $lastMigration = $versions->max('migratedAt');
            $userCount = (int) ($userCountByTenant[$tenant->id] ?? 0);
            $pendingReports = (int) ($pendingByTenant[$tenant->id] ?? 0);
            $failedReports = (int) ($failedByTenant[$tenant->id] ?? 0);

            return new TenantHealthData(
                id: $tenant->id,
                name: $tenant->name,
                slug: $tenant->slug,
                isActive: $tenant->isActive,
                hasReadReplica: $tenant->hasReadReplica,
                userCount: $userCount,
                migrationCount: $versions->count(),
                lastMigration: $lastMigration,
                pendingReports: $pendingReports,
                failedReports: $failedReports,
                lastReportAt: $lastReportByTenant[$tenant->id] ?? null,
                healthStatus: $this->computeHealthStatus(
                    isActive: $tenant->isActive,
                    failedReports: $failedReports,
                    userCount: $userCount,
                    lastMigration: $lastMigration ? Carbon::parse($lastMigration) : null,
                ),
            );
        });
    }

    /** @param Collection<int, TenantHealthData> $tenants */
    public function getSummary(Collection $tenants): TenantHealthSummaryData
    {
        return new TenantHealthSummaryData(
            total: $tenants->count(),
            healthy: $tenants->where('healthStatus', 'healthy')->count(),
            warning: $tenants->where('healthStatus', 'warning')->count(),
            critical: $tenants->where('healthStatus', 'critical')->count(),
        );
    }

    public function computeHealthStatus(
        bool $isActive,
        int $failedReports,
        int $userCount,
        ?Carbon $lastMigration,
    ): string {
        if (! $isActive || $failedReports > 0) {
            return 'critical';
        }

        $migrationStale = $lastMigration === null || $lastMigration->diffInDays(now()) > 30;

        if ($userCount === 0 || $migrationStale) {
            return 'warning';
        }

        return 'healthy';
    }
}
