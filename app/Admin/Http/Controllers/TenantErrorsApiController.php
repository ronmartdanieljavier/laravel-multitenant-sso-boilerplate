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
     *
     * @param  Request  $request  the incoming request
     * @param  Tenant  $tenant  the tenant for which to list error logs
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
     *
     * @param  Tenant  $tenant  the tenant for which to get the error log
     * @param  TenantErrorLog  $error  the error log entry to retrieve
     */
    public function show(Tenant $tenant, TenantErrorLog $error): JsonResponse
    {
        abort_if($error->tenant_id !== $tenant->id, 404);

        return response()->json(['data' => $this->service->getById($error->id)]);
    }

    /**
     * Look up an error log by its public error code.
     *
     * @param  string  $errorCode  the error code to look up
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
     *
     * @param  Tenant  $tenant  the tenant for which to resolve the error
     * @param  TenantErrorLog  $error  the error log entry to resolve
     */
    public function resolve(Tenant $tenant, TenantErrorLog $error): JsonResponse
    {
        abort_if($error->tenant_id !== $tenant->id, 404);

        $this->service->resolve($error->id);

        return response()->json(['message' => 'Marked as resolved.']);
    }

    /**
     * Mark an error as unresolved.
     *
     * @param  Tenant  $tenant  the tenant for which to unresolve the error
     * @param  TenantErrorLog  $error  the error log entry to unresolve
     */
    public function unresolve(Tenant $tenant, TenantErrorLog $error): JsonResponse
    {
        abort_if($error->tenant_id !== $tenant->id, 404);

        $this->service->unresolve($error->id);

        return response()->json(['message' => 'Reopened.']);
    }

    /**
     * Delete an error log entry.
     *
     * @param  Tenant  $tenant  the tenant for which to delete the error log
     * @param  TenantErrorLog  $error  the error log entry to delete
     */
    public function destroy(Tenant $tenant, TenantErrorLog $error): JsonResponse
    {
        abort_if($error->tenant_id !== $tenant->id, 404);

        $this->service->delete($error->id);

        return response()->json(['message' => 'Error log deleted.']);
    }
}
