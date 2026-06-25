<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Services\TenantErrorLogService;
use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Models\Central\TenantErrorLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantErrorsApiController extends Controller
{
    public function __construct(
        private readonly TenantErrorLogService $service,
    ) {}

    /**
     * List all error logs for a tenant.
     */
    public function index(Request $request, Tenant $tenant): JsonResponse
    {
        $severity = $request->query('severity');
        $unresolvedOnly = (bool) $request->query('unresolved');

        return response()->json([
            'data' => $this->service->listForTenant($tenant->id, $severity ?: null, $unresolvedOnly),
        ]);
    }

    /**
     * Get a single error log entry by ID.
     */
    public function show(Tenant $tenant, TenantErrorLog $error): JsonResponse
    {
        abort_if($error->tenant_id !== $tenant->id, 404);

        return response()->json(['data' => $this->service->getById($error->id)]);
    }

    /**
     * Look up an error log by its public error code.
     */
    public function findByCode(string $errorCode): JsonResponse
    {
        $log = $this->service->getByCode($errorCode);

        if ($log === null) {
            return response()->json(['message' => 'Error code not found.'], 404);
        }

        return response()->json(['data' => $log]);
    }

    /**
     * Mark an error as resolved.
     */
    public function resolve(Tenant $tenant, TenantErrorLog $error): JsonResponse
    {
        abort_if($error->tenant_id !== $tenant->id, 404);

        $this->service->resolve($error->id);

        return response()->json(['message' => 'Marked as resolved.']);
    }

    /**
     * Mark an error as unresolved.
     */
    public function unresolve(Tenant $tenant, TenantErrorLog $error): JsonResponse
    {
        abort_if($error->tenant_id !== $tenant->id, 404);

        $this->service->unresolve($error->id);

        return response()->json(['message' => 'Reopened.']);
    }

    /**
     * Delete an error log entry.
     */
    public function destroy(Tenant $tenant, TenantErrorLog $error): JsonResponse
    {
        abort_if($error->tenant_id !== $tenant->id, 404);

        $this->service->delete($error->id);

        return response()->json(['message' => 'Error log deleted.']);
    }
}
