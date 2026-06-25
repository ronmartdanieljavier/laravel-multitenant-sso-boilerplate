<?php

namespace App\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Repositories\Central\ReportRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantReportQueueController extends Controller
{
    public function __construct(
        private readonly ReportRepository $reportRepository,
    ) {}

    public function index(Request $request): Response
    {
        $tenant = $request->attributes->get('current_tenant');

        abort_if($tenant === null, 404);

        $reports = $this->reportRepository->listForTenant($tenant->id);

        return Inertia::render('Tenant/ReportQueue', [
            'reports' => $reports,
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug],
        ]);
    }
}
