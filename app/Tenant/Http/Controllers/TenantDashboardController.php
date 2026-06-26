<?php

namespace App\Tenant\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenant\Services\TenantDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantDashboardController extends Controller
{
    public function __construct(
        private readonly TenantDashboardService $dashboardService,
    ) {}

    public function index(Request $request): Response
    {
        $tenant = $request->attributes->get('current_tenant');

        abort_if($tenant === null, 404);

        $dashboard = $this->dashboardService->getDashboard($tenant->id);

        return Inertia::render('Tenant/Index', [
            'stats' => $dashboard->stats,
            'recentReports' => $dashboard->recentReports->values(),
            'recentErrors' => $dashboard->recentErrors->values(),
            'recentDocuments' => $dashboard->recentDocuments->values(),
        ]);
    }
}
