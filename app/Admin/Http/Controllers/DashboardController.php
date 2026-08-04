<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Services\DashboardService;
use App\Admin\Services\TenantErrorLogService;
use App\Admin\Services\UserManagementService;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService,
        private UserManagementService $userManagementService,
        private TenantErrorLogService $errorLogService,
    ) {}

    /**
     * Display the dashboard statistics.
     */
    public function index(): Response
    {
        return Inertia::render('Admin/Index', [
            'stats' => $this->dashboardService->getStats(),
            'healthSummary' => $this->dashboardService->getHealthSummary(),
            'recentUsers' => $this->userManagementService->recentUsers(),
            'pendingUsers' => $this->userManagementService->pendingUsers(),
            'unresolvedErrors' => $this->errorLogService->getUnresolvedSummary(),
            'reportQueue' => $this->dashboardService->getReportQueueSummary(),
            'migrationCompliance' => $this->dashboardService->getMigrationComplianceSummary(),
        ]);
    }
}
