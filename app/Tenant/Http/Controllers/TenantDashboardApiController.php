<?php

namespace App\Tenant\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenant\Services\TenantDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantDashboardApiController extends Controller
{
    public function __construct(
        private readonly TenantDashboardService $dashboardService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('current_tenant');

        abort_if($tenant === null, 404);

        $dashboard = $this->dashboardService->getDashboard($tenant->id);

        return response()->json([
            'data' => [
                'stats' => $dashboard->stats,
                'recentReports' => $dashboard->recentReports->values(),
                'recentErrors' => $dashboard->recentErrors->values(),
                'recentDocuments' => $dashboard->recentDocuments->values(),
            ],
        ]);
    }
}
