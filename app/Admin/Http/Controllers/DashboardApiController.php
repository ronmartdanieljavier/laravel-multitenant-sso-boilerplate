<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Services\DashboardService;
use App\Admin\Services\TenantErrorLogService;
use App\Admin\Services\UserManagementService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DashboardApiController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService,
        private UserManagementService $userManagementService,
        private TenantErrorLogService $errorLogService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboardService->getStats(),
            'health_summary' => $this->dashboardService->getHealthSummary(),
            'recent_users' => $this->userManagementService->recentUsers(),
            'pending_users' => $this->userManagementService->pendingUsers(),
            'unresolved_errors' => $this->errorLogService->getUnresolvedSummary(),
            'report_queue' => $this->dashboardService->getReportQueueSummary(),
            'migration_compliance' => $this->dashboardService->getMigrationComplianceSummary(),
        ]);
    }
}
