<?php

namespace App\TenantJobs\Http\Controllers;

use App\Http\Controllers\Controller;
use App\TenantJobs\Services\TenantJobsPortalService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantJobsController extends Controller
{
    public function __construct(
        private readonly TenantJobsPortalService $service,
    ) {}

    public function index(Request $request): Response
    {
        $tenant = $request->attributes->get('current_tenant');
        abort_if($tenant === null, 404);

        $status = $request->query('status');
        $perPage = (int) ($request->query('per_page', 20));

        return Inertia::render('Tenant/Jobs', [
            'jobs' => $this->service->listForTenant($tenant->id, $status ?: null, $perPage),
            'filters' => ['status' => $status ?: null],
        ]);
    }
}
