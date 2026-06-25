<?php

namespace App\Admin\Services;

use App\Admin\Data\DashboardStatsData;
use App\Admin\Data\MigrationComplianceSummaryData;
use App\Admin\Data\MigrationComplianceTenantData;
use App\Admin\Data\ReportQueueSummaryData;
use App\Admin\Data\ReportQueueTenantData;
use App\Admin\Data\TenantHealthSummaryData;
use App\Reports\Enums\ReportStatus;
use App\Repositories\Central\AppRepository;
use App\Repositories\Central\ReportRepository;
use App\Repositories\Central\TenantRepository;
use App\Repositories\Central\UserRepository;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(
        protected UserRepository $userRepository,
        protected AppRepository $appRepository,
        protected TenantRepository $tenantRepository,
        protected TenantHealthService $tenantHealthService,
        protected ReportRepository $reportRepository,
    ) {}

    /**
     * Get dashboard stats data.
     */
    public function getHealthSummary(): TenantHealthSummaryData
    {
        $tenants = $this->tenantHealthService->getTenants();

        return $this->tenantHealthService->getSummary($tenants);
    }

    /**
     * Get migration compliance summary
     */
    public function getMigrationComplianceSummary(): MigrationComplianceSummaryData
    {
        $available = $this->tenantRepository->countAvailableMigrations();
        $tenants = $this->tenantHealthService->getTenants();

        $behind = $tenants
            ->filter(fn ($t) => $t->migrationCount < $available)
            ->map(fn ($t) => new MigrationComplianceTenantData(
                id: $t->id,
                name: $t->name,
                slug: $t->slug,
                applied: $t->migrationCount,
                available: $available,
            ))
            ->values()
            ->all();

        $total = $tenants->count();

        return new MigrationComplianceSummaryData(
            total: $total,
            upToDate: $total - count($behind),
            behindCount: count($behind),
            availableMigrations: $available,
            behind: $behind,
        );
    }

    /**
     * Get report queue summary
     */
    public function getReportQueueSummary(): ReportQueueSummaryData
    {
        $rows = $this->reportRepository->countQueueStatusByTenant();

        $totals = $rows->groupBy('status')->map(fn (Collection $g) => $g->sum('count'));

        $byTenant = $rows
            ->groupBy('tenant_id')
            ->map(function (Collection $group): ReportQueueTenantData {
                $counts = $group->pluck('count', 'status');

                return new ReportQueueTenantData(
                    tenantId: (int) $group->value('tenant_id'),
                    tenantName: (string) $group->value('tenant_name'),
                    tenantSlug: (string) $group->value('tenant_slug'),
                    pending: (int) ($counts[ReportStatus::Pending->value] ?? 0),
                    processing: (int) ($counts[ReportStatus::Processing->value] ?? 0),
                    failed: (int) ($counts[ReportStatus::Failed->value] ?? 0),
                );
            })
            ->sortByDesc(fn (ReportQueueTenantData $d) => $d->pending + $d->processing + $d->failed)
            ->values()
            ->all();

        return new ReportQueueSummaryData(
            pending: (int) ($totals[ReportStatus::Pending->value] ?? 0),
            processing: (int) ($totals[ReportStatus::Processing->value] ?? 0),
            failed: (int) ($totals[ReportStatus::Failed->value] ?? 0),
            byTenant: $byTenant,
        );
    }

    /**
     * Get dashboard stats data.
     */
    public function getStats(): DashboardStatsData
    {
        $activeUsers = $this->userRepository->countActive();
        $pendingUsers = $this->userRepository->countPendingInvitation();
        $activeSsoSessions = $this->userRepository->countSsoSessions();

        return new DashboardStatsData(
            totalUsers: $activeUsers + $pendingUsers,
            activeUsers: $activeUsers,
            pendingInvitationUsers: $pendingUsers,
            activeApps: $this->appRepository->countActive(),
            activeTenants: $this->tenantRepository->countActive(),
            activeSsoSessions: $activeSsoSessions,
        );
    }
}
