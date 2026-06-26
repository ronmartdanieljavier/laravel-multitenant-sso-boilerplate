<?php

namespace App\Tenant\Services;

use App\Repositories\Central\DocumentRepository;
use App\Repositories\Central\ReportRepository;
use App\Repositories\Central\TenantErrorLogRepository;
use App\Tenant\Data\TenantDashboardData;
use App\Tenant\Data\TenantDashboardStatsData;

class TenantDashboardService
{
    public function __construct(
        private readonly ReportRepository $reportRepository,
        private readonly TenantErrorLogRepository $errorLogRepository,
        private readonly DocumentRepository $documentRepository,
    ) {}

    public function getDashboard(int $tenantId): TenantDashboardData
    {
        $reportSummary = $this->reportRepository->summaryForTenant($tenantId);

        $allErrors = $this->errorLogRepository->listForTenant($tenantId);
        $unresolvedErrors = $allErrors->filter(fn ($e) => $e->resolvedAt === null);
        $criticalErrors = $unresolvedErrors->filter(fn ($e) => $e->severity === 'critical');

        $recentErrors = $unresolvedErrors->take(5)->values();
        $recentReports = $this->reportRepository->recentForTenant($tenantId, 5);
        $recentDocuments = $this->documentRepository->recentList(5);
        $totalDocuments = $this->documentRepository->countAll();

        $stats = new TenantDashboardStatsData(
            pendingReports: $reportSummary['pending'],
            processingReports: $reportSummary['processing'],
            failedReports: $reportSummary['failed'],
            successReportsThisMonth: $reportSummary['successThisMonth'],
            unresolvedErrors: $unresolvedErrors->count(),
            criticalErrors: $criticalErrors->count(),
            totalDocuments: $totalDocuments,
        );

        return new TenantDashboardData(
            stats: $stats,
            recentReports: $recentReports,
            recentErrors: $recentErrors,
            recentDocuments: $recentDocuments,
        );
    }
}
