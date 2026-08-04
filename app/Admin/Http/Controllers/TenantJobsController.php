<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Services\TenantJobsAdminService;
use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Repositories\Central\TenantRepository;
use App\TenantJobs\Enums\TenantJobStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantJobsController extends Controller
{
    public function __construct(
        private readonly TenantJobsAdminService $service,
        private readonly TenantRepository $tenantRepository,
    ) {}

    /**
     * Display a list of tenant jobs, optionally filtered by tenant ID and status.
     *
     * @param  Request  $request  the incoming request
     */
    public function index(Request $request): Response
    {
        $tenantId = $request->query('tenant_id') ? (int) $request->query('tenant_id') : null;
        $status = $request->query('status');
        $perPage = min((int) ($request->query('per_page', 20)), 100);
        $statusEnum = $status ? TenantJobStatus::tryFrom($status) : null;

        return Inertia::render('Admin/Jobs/Index', [
            'jobs' => $this->service->listAll($perPage, $tenantId, $statusEnum),
            'tenants' => $this->tenantRepository->pluckNames()->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'filters' => ['tenant_id' => $tenantId, 'status' => $status ?: null],
        ]);
    }

    /**
     * Display a list of tenant jobs for a specific tenant, optionally filtered by status.
     *
     * @param  Request  $request  the incoming request
     * @param  Tenant  $tenant  the tenant for which to list jobs
     */
    public function indexForTenant(Request $request, Tenant $tenant): Response
    {
        $status = $request->query('status');
        $perPage = min((int) ($request->query('per_page', 20)), 100);
        $statusEnum = $status ? TenantJobStatus::tryFrom($status) : null;

        return Inertia::render('Admin/Tenants/Jobs', [
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug],
            'jobs' => $this->service->listForTenant($tenant->id, $statusEnum, $perPage),
            'filters' => ['status' => $status ?: null],
        ]);
    }
}
