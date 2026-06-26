<?php

namespace App\TenantErrors\Http\Controllers;

use App\Http\Controllers\Controller;
use App\TenantErrors\Services\TenantErrorsPortalService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantErrorsController extends Controller
{
    public function __construct(
        private readonly TenantErrorsPortalService $service,
    ) {}

    public function index(Request $request): Response
    {
        $tenant = $request->attributes->get('current_tenant');

        abort_if($tenant === null, 404);

        $severity = $request->query('severity');
        $unresolvedOnly = (bool) $request->query('unresolved');

        return Inertia::render('Tenant/ErrorLogs', [
            'logs' => $this->service->listForTenant($tenant->id, $severity ?: null, $unresolvedOnly),
            'filters' => ['severity' => $severity, 'unresolved' => $unresolvedOnly],
        ]);
    }

    public function show(Request $request, int $errorId): Response
    {
        $tenant = $request->attributes->get('current_tenant');

        abort_if($tenant === null, 404);

        return Inertia::render('Tenant/ErrorLog', [
            'log' => $this->service->getForTenant($tenant->id, $errorId),
        ]);
    }
}
