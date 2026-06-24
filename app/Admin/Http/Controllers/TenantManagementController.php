<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Data\CreateTenantData;
use App\Admin\Data\UpdateTenantData;
use App\Admin\Http\Requests\CreateTenantRequest;
use App\Admin\Http\Requests\UpdateTenantRequest;
use App\Admin\Services\TenantManagementService;
use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantManagementController extends Controller
{
    public function __construct(
        private TenantManagementService $service,
    ) {}

    /**
     * Get a list of all tenants with their details and health status.
     */
    public function index(): Response
    {
        $tenants = $this->service->list();

        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => $tenants,
            'summary' => $this->service->getSummary($tenants),
        ]);
    }

    /**
     * Create a new tenant.
     */
    public function store(CreateTenantRequest $request): RedirectResponse
    {
        $this->service->create(CreateTenantData::from($request->validated()));

        return redirect()->route('admin.tenants')->with('success', 'Tenant created and migrations run.');
    }

    /**
     * Update a tenant's information.
     */
    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->service->update($tenant->id, UpdateTenantData::from($request->validated()));

        return redirect()->route('admin.tenants')->with('success', 'Tenant updated.');
    }

    /**
     * Set a tenant's active status.
     */
    public function setActive(Request $request, Tenant $tenant): RedirectResponse
    {
        $isActive = (bool) $request->input('is_active');
        $this->service->setActive($tenant->id, $isActive);

        $message = $isActive ? 'Tenant activated.' : 'Tenant deactivated and user tokens revoked.';

        return redirect()->route('admin.tenants')->with('success', $message);
    }

    /**
     * Delete a tenant.
     */
    public function destroy(Tenant $tenant): RedirectResponse
    {
        $this->service->delete($tenant->id);

        return redirect()->route('admin.tenants')->with('success', 'Tenant deleted.');
    }

    /**
     * Run migrations for a specific tenant.
     */
    public function migrate(Tenant $tenant): RedirectResponse
    {
        $this->service->runMigrations($tenant->id);

        return redirect()->route('admin.tenants')->with('success', "Migrations run for {$tenant->name}.");
    }

    /**
     * Run migrations for all tenants.
     */
    public function migrateAll(): RedirectResponse
    {
        $this->service->runMigrations();

        return redirect()->route('admin.tenants')->with('success', 'Migrations run for all tenants.');
    }
}
