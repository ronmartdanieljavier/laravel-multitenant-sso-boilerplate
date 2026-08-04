<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Services\TenantJobsAdminService;
use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\TenantJobs\Enums\TenantJobStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantJobsApiController extends Controller
{
    public function __construct(
        private readonly TenantJobsAdminService $service,
    ) {}

    /**
     * List all tenant jobs, optionally filtered by tenant ID and status.
     *
     * @param  Request  $request  the incoming request
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant_id') ? (int) $request->query('tenant_id') : null;
        $status = $request->query('status');
        $perPage = min((int) ($request->query('per_page', 20)), 100);
        $statusEnum = $status ? TenantJobStatus::tryFrom($status) : null;

        return response()->json([
            'data' => $this->service->listAll($perPage, $tenantId, $statusEnum),
        ]);
    }

    /**
     * List tenant jobs for a specific tenant, optionally filtered by status.
     *
     * @param  Request  $request  the incoming request
     * @param  Tenant  $tenant  the tenant for which to list jobs
     */
    public function indexForTenant(Request $request, Tenant $tenant): JsonResponse
    {
        $status = $request->query('status');
        $perPage = min((int) ($request->query('per_page', 20)), 100);
        $statusEnum = $status ? TenantJobStatus::tryFrom($status) : null;

        return response()->json([
            'data' => $this->service->listForTenant($tenant->id, $statusEnum, $perPage),
        ]);
    }
}
