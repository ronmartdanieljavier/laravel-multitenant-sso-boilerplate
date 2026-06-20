<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Report;
use App\Models\Central\Tenant;
use App\Reports\Enums\ReportStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TenantHealthController extends Controller
{
    public function index(): Response
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

        $enriched = $tenants->map(function (Tenant $tenant) use (
            $pendingByTenant,
            $failedByTenant,
            $lastReportByTenant,
            $userCountByTenant,
        ) {
            $lastMigration = $tenant->migrationVersions->max('migrated_at');
            $migrationCount = $tenant->migrationVersions->count();
            $userCount = (int) ($userCountByTenant[$tenant->id] ?? 0);
            $pendingReports = (int) ($pendingByTenant[$tenant->id] ?? 0);
            $failedReports = (int) ($failedByTenant[$tenant->id] ?? 0);
            $lastReportAt = $lastReportByTenant[$tenant->id] ?? null;

            $healthStatus = $this->computeHealthStatus(
                isActive: $tenant->is_active,
                failedReports: $failedReports,
                userCount: $userCount,
                lastMigration: $lastMigration ? Carbon::parse($lastMigration) : null,
            );

            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'is_active' => $tenant->is_active,
                'has_read_replica' => $tenant->hasReadReplica(),
                'user_count' => $userCount,
                'migration_count' => $migrationCount,
                'last_migration' => $lastMigration,
                'pending_reports' => $pendingReports,
                'failed_reports' => $failedReports,
                'last_report_at' => $lastReportAt,
                'health_status' => $healthStatus,
            ];
        });

        $summary = [
            'total' => $enriched->count(),
            'healthy' => $enriched->where('health_status', 'healthy')->count(),
            'warning' => $enriched->where('health_status', 'warning')->count(),
            'critical' => $enriched->where('health_status', 'critical')->count(),
        ];

        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => $enriched->values(),
            'summary' => $summary,
        ]);
    }

    private function computeHealthStatus(
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
