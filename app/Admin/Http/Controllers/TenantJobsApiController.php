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

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant_id') ? (int) $request->query('tenant_id') : null;
        $status = $request->query('status');
        $perPage = (int) ($request->query('per_page', 20));
        $statusEnum = $status ? TenantJobStatus::tryFrom($status) : null;

        return response()->json([
            'data' => $this->service->listAll($perPage, $tenantId, $statusEnum),
        ]);
    }

    public function indexForTenant(Request $request, Tenant $tenant): JsonResponse
    {
        $status = $request->query('status');
        $perPage = (int) ($request->query('per_page', 20));
        $statusEnum = $status ? TenantJobStatus::tryFrom($status) : null;

        return response()->json([
            'data' => $this->service->listForTenant($tenant->id, $statusEnum, $perPage),
        ]);
    }
}
