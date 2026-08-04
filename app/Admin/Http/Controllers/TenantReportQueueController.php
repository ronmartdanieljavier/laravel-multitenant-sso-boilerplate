<?php

namespace App\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Repositories\Central\ReportRepository;
use Inertia\Inertia;
use Inertia\Response;

class TenantReportQueueController extends Controller
{
    public function __construct(
        private readonly ReportRepository $reportRepository,
    ) {}

    /**
     * List all report jobs for the given tenant.
     *
     * @param  Tenant  $tenant  the tenant for which to list report jobs
     */
    public function index(Tenant $tenant): Response
    {
        return Inertia::render('Admin/Tenants/ReportQueue', [
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug],
            'reports' => $this->reportRepository->listForTenant($tenant->id),
        ]);
    }
}
