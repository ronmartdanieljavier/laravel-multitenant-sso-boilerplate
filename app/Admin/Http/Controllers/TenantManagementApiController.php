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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantManagementApiController extends Controller
{
    public function __construct(
        private TenantManagementService $service,
    ) {}

    /**
     * Get a list of all tenants with their details and health status.
     */
    public function index(): JsonResponse
    {
        $tenants = $this->service->list();

        return response()->json([
            'data' => $tenants,
            'summary' => $this->service->getSummary($tenants),
        ]);
    }

    /**
     * Create a new tenant.
     *
     * @param  CreateTenantRequest  $request  validated request data
     */
    public function store(CreateTenantRequest $request): JsonResponse
    {
        $tenant = $this->service->create(CreateTenantData::from($request->validated()));

        return response()->json(['data' => $tenant], 201);
    }

    /**
     * Update a tenant's information.
     *
     * @param  UpdateTenantRequest  $request  validated request data
     */
    public function update(UpdateTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $updated = $this->service->update($tenant->id, UpdateTenantData::from($request->validated()));

        return response()->json(['data' => $updated]);
    }

    /**
     * Set a tenant's active status.
     *
     * @param  Request  $request  the incoming request
     * @param  Tenant  $tenant  the tenant for which to set the active status
     */
    public function setActive(Request $request, Tenant $tenant): JsonResponse
    {
        $isActive = (bool) $request->input('is_active');
        $this->service->setActive($tenant->id, $isActive);

        $message = $isActive ? 'Tenant activated.' : 'Tenant deactivated and user tokens revoked.';

        return response()->json(['message' => $message]);
    }

    /**
     * Delete a tenant.
     *
     * @param  Tenant  $tenant  the tenant to be deleted
     */
    public function destroy(Tenant $tenant): JsonResponse
    {
        $this->service->delete($tenant->id);

        return response()->json(['message' => 'Tenant deleted.']);
    }

    /**
     * Set a tenant's maintenance mode.
     *
     * @param  SetTenantMaintenanceRequest  $request  validated request data
     * @param  Tenant  $tenant  the tenant for which to set maintenance mode
     */
    public function setMaintenance(SetTenantMaintenanceRequest $request, Tenant $tenant): JsonResponse
    {
        $isMaintenance = (bool) $request->input('is_maintenance');
        $this->service->setMaintenance($tenant->id, $isMaintenance);

        $message = $isMaintenance
            ? 'Tenant placed in maintenance mode and user tokens revoked.'
            : 'Tenant maintenance mode disabled.';

        return response()->json(['message' => $message]);
    }

    /**
     * Set maintenance mode for all tenants.
     *
     * @param  SetTenantMaintenanceRequest  $request  validated request data
     */
    public function setMaintenanceAll(SetTenantMaintenanceRequest $request): JsonResponse
    {
        $isMaintenance = (bool) $request->input('is_maintenance');
        $this->service->setMaintenanceAll($isMaintenance);

        $message = $isMaintenance
            ? 'All tenants placed in maintenance mode and user tokens revoked.'
            : 'Maintenance mode disabled for all tenants.';

        return response()->json(['message' => $message]);
    }

    /**
     * Run migrations for a specific tenant.
     *
     * @param  Tenant  $tenant  the tenant for which to run migrations
     */
    public function migrate(Tenant $tenant): JsonResponse
    {
        $this->service->runMigrations($tenant->id);

        return response()->json(['message' => "Migrations run for {$tenant->name}."]);
    }

    /**
     * Run migrations for all tenants.
     */
    public function migrateAll(): JsonResponse
    {
        $this->service->runMigrations();

        return response()->json(['message' => 'Migrations run for all tenants.']);
    }
}
