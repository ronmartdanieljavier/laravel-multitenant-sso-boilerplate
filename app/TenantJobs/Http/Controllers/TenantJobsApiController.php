<?php

namespace App\TenantJobs\Http\Controllers;

use App\Http\Controllers\Controller;
use App\TenantJobs\Services\TenantJobsPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantJobsApiController extends Controller
{
    public function __construct(
        private readonly TenantJobsPortalService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('current_tenant');
        abort_if($tenant === null, 404);

        $status = $request->query('status');
        $perPage = (int) ($request->query('per_page', 20));

        return response()->json(
            $this->service->listForTenant($tenant->id, $status ?: null, $perPage)
        );
    }
}
