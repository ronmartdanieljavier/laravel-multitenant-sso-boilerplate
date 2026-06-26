<?php

namespace App\TenantErrors\Http\Controllers;

use App\Http\Controllers\Controller;
use App\TenantErrors\Services\TenantErrorsPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantErrorsApiController extends Controller
{
    public function __construct(
        private readonly TenantErrorsPortalService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('current_tenant');

        abort_if($tenant === null, 404);

        $severity = $request->query('severity');
        $unresolvedOnly = (bool) $request->query('unresolved');

        return response()->json([
            'data' => $this->service->listForTenant($tenant->id, $severity ?: null, $unresolvedOnly)->values(),
        ]);
    }

    public function show(Request $request, int $errorId): JsonResponse
    {
        $tenant = $request->attributes->get('current_tenant');

        abort_if($tenant === null, 404);

        return response()->json([
            'data' => $this->service->getForTenant($tenant->id, $errorId),
        ]);
    }
}
