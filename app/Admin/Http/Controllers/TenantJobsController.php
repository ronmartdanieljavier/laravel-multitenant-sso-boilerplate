<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Services\TenantJobsAdminService;
use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\TenantJobs\Enums\TenantJobStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantJobsController extends Controller
{
    public function __construct(
        private readonly TenantJobsAdminService $service,
    ) {}

    public function index(Request $request): Response
    {
        $tenantId = $request->query('tenant_id') ? (int) $request->query('tenant_id') : null;
        $status = $request->query('status');
        $perPage = (int) ($request->query('per_page', 20));
        $statusEnum = $status ? TenantJobStatus::tryFrom($status) : null;

        return Inertia::render('Admin/Jobs/Index', [
            'jobs' => $this->service->listAll($perPage, $tenantId, $statusEnum),
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name']),
            'filters' => ['tenant_id' => $tenantId, 'status' => $status ?: null],
        ]);
    }

    public function indexForTenant(Request $request, Tenant $tenant): Response
    {
        $status = $request->query('status');
        $perPage = (int) ($request->query('per_page', 20));
        $statusEnum = $status ? TenantJobStatus::tryFrom($status) : null;

        return Inertia::render('Admin/Tenants/Jobs', [
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug],
            'jobs' => $this->service->listForTenant($tenant->id, $statusEnum, $perPage),
            'filters' => ['status' => $status ?: null],
        ]);
    }
}
