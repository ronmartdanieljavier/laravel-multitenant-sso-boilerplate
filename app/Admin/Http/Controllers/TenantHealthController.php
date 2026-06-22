<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Services\TenantHealthService;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class TenantHealthController extends Controller
{
    public function __construct(
        private readonly TenantHealthService $tenantHealthService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $tenants = $this->tenantHealthService->getTenants();

        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => $tenants->values(),
            'summary' => $this->tenantHealthService->getSummary($tenants),
        ]);
    }
}
