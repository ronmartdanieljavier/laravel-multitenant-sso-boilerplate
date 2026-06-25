<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Services\TenantErrorLogService;
use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Models\Central\TenantErrorLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantErrorsController extends Controller
{
    public function __construct(
        private readonly TenantErrorLogService $service,
    ) {}

    /**
     * List all error logs for a tenant.
     */
    public function index(Request $request, Tenant $tenant): Response
    {
        $severity = $request->query('severity');
        $unresolvedOnly = (bool) $request->query('unresolved');

        return Inertia::render('Admin/Tenants/Errors', [
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug],
            'logs' => $this->service->listForTenant($tenant->id, $severity ?: null, $unresolvedOnly),
            'filters' => ['severity' => $severity, 'unresolved' => $unresolvedOnly],
        ]);
    }

    /**
     * Show a single error log entry.
     */
    public function show(Tenant $tenant, TenantErrorLog $error): Response
    {
        abort_if($error->tenant_id !== $tenant->id, 404);

        return Inertia::render('Admin/Tenants/ErrorDetail', [
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug],
            'log' => $this->service->getById($error->id),
        ]);
    }

    /**
     * Mark an error as resolved.
     */
    public function resolve(Tenant $tenant, TenantErrorLog $error): RedirectResponse
    {
        abort_if($error->tenant_id !== $tenant->id, 404);

        $this->service->resolve($error->id);

        return redirect()->back()->with('success', 'Error marked as resolved.');
    }

    /**
     * Mark an error as unresolved.
     */
    public function unresolve(Tenant $tenant, TenantErrorLog $error): RedirectResponse
    {
        abort_if($error->tenant_id !== $tenant->id, 404);

        $this->service->unresolve($error->id);

        return redirect()->back()->with('success', 'Error reopened.');
    }

    /**
     * Delete an error log entry.
     */
    public function destroy(Tenant $tenant, TenantErrorLog $error): RedirectResponse
    {
        abort_if($error->tenant_id !== $tenant->id, 404);

        $this->service->delete($error->id);

        return redirect()->route('admin.tenants.errors', $tenant)->with('success', 'Error log deleted.');
    }
}
