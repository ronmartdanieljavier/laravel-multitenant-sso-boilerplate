<?php

namespace App\Admin\Services;

use App\Admin\Data\TenantHealthData;
use App\Admin\Data\TenantHealthSummaryData;
use App\Models\Central\Report;
use App\Models\Central\Tenant;
use App\Reports\Enums\ReportStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TenantHealthService
{
    /**
     * Get a list of tenants with their health data.
     *
     * @return Collection<int, TenantHealthData>|\Illuminate\Database\Eloquent\Collection<int, TenantHealthData>
     */
    public function getTenants(): Collection
    {
        $tenants = Tenant::with('migrationVersions')->get();

        $pendingByTenant = Report::query()
            ->where('status', ReportStatus::Pending)
            ->selectRaw('tenant_id, COUNT(*) as count')
            ->groupBy('tenant_id')
            ->pluck('count', 'tenant_id');

        $failedByTenant = Report::query()
            ->where('status', ReportStatus::Failed)
            ->selectRaw('tenant_id, COUNT(*) as count')
            ->groupBy('tenant_id')
            ->pluck('count', 'tenant_id');

        $lastReportByTenant = Report::query()
            ->where('status', ReportStatus::Success)
            ->selectRaw('tenant_id, MAX(completed_at) as last_report_at')
            ->groupBy('tenant_id')
            ->pluck('last_report_at', 'tenant_id');

        $userCountByTenant = DB::table('user_app_tenants')
            ->selectRaw('tenant_id, COUNT(DISTINCT user_id) as count')
            ->groupBy('tenant_id')
            ->pluck('count', 'tenant_id');

        return $tenants->map(function (Tenant $tenant) use (
            $pendingByTenant,
            $failedByTenant,
            $lastReportByTenant,
            $userCountByTenant,
        ) {
            $lastMigration = $tenant->migrationVersions->max('migrated_at');
            $userCount = (int) ($userCountByTenant[$tenant->id] ?? 0);
            $pendingReports = (int) ($pendingByTenant[$tenant->id] ?? 0);
            $failedReports = (int) ($failedByTenant[$tenant->id] ?? 0);

            return new TenantHealthData(
                id: $tenant->id,
                name: $tenant->name,
                slug: $tenant->slug,
                isActive: $tenant->is_active,
                hasReadReplica: $tenant->hasReadReplica(),
                userCount: $userCount,
                migrationCount: $tenant->migrationVersions->count(),
                lastMigration: $lastMigration,
                pendingReports: $pendingReports,
                failedReports: $failedReports,
                lastReportAt: $lastReportByTenant[$tenant->id] ?? null,
                healthStatus: $this->computeHealthStatus(
                    isActive: $tenant->is_active,
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

    /**
     * Compute the health status of a tenant based on their data.
     */
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
