<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Data\CreateTenantData;
use App\Admin\Data\UpdateTenantData;
use App\Admin\Http\Requests\CreateTenantRequest;
use App\Admin\Http\Requests\SetTenantMaintenanceRequest;
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
     *
     * @param  CreateTenantRequest  $request  validated request data
     */
    public function store(CreateTenantRequest $request): RedirectResponse
    {
        $this->service->create(CreateTenantData::from($request->validated()));

        return redirect()->route('admin.tenants')->with('success', 'Tenant created and migrations run.');
    }

    /**
     * Update a tenant's information.
     *
     * @param  UpdateTenantRequest  $request  validated request data
     * @param  Tenant  $tenant  the tenant to be updated
     */
    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->service->update($tenant->id, UpdateTenantData::from($request->validated()));

        return redirect()->route('admin.tenants')->with('success', 'Tenant updated.');
    }

    /**
     * Set a tenant's active status.
     *
     * @param  Request  $request  the incoming request
     * @param  Tenant  $tenant  the tenant for which to set the active status
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
     *
     * @param  Tenant  $tenant  the tenant to be deleted
     */
    public function destroy(Tenant $tenant): RedirectResponse
    {
        $this->service->delete($tenant->id);

        return redirect()->route('admin.tenants')->with('success', 'Tenant deleted.');
    }

    /**
     * Set a tenant's maintenance mode.
     *
     * @param  SetTenantMaintenanceRequest  $request  validated request data
     * @param  Tenant  $tenant  the tenant for which to set maintenance mode
     */
    public function setMaintenance(SetTenantMaintenanceRequest $request, Tenant $tenant): RedirectResponse
    {
        $isMaintenance = (bool) $request->input('is_maintenance');
        $this->service->setMaintenance($tenant->id, $isMaintenance);

        $message = $isMaintenance
            ? 'Tenant placed in maintenance mode and user tokens revoked.'
            : 'Tenant maintenance mode disabled.';

        return redirect()->route('admin.tenants')->with('success', $message);
    }

    /**
     * Set maintenance mode for all tenants.
     *
     * @param  SetTenantMaintenanceRequest  $request  validated request data
     */
    public function setMaintenanceAll(SetTenantMaintenanceRequest $request): RedirectResponse
    {
        $isMaintenance = (bool) $request->input('is_maintenance');
        $this->service->setMaintenanceAll($isMaintenance);

        $message = $isMaintenance
            ? 'All tenants placed in maintenance mode and user tokens revoked.'
            : 'Maintenance mode disabled for all tenants.';

        return redirect()->route('admin.tenants')->with('success', $message);
    }

    /**
     * Run migrations for a specific tenant.
     *
     * @param  Tenant  $tenant  the tenant for which to run migrations
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
