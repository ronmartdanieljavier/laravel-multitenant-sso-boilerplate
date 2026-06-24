<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Data\CreateTenantData;
use App\Admin\Data\UpdateTenantData;
use App\Admin\Http\Requests\CreateTenantRequest;
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
     */
    public function store(CreateTenantRequest $request): JsonResponse
    {
        $tenant = $this->service->create(CreateTenantData::from($request->validated()));

        return response()->json(['data' => $tenant], 201);
    }

    /**
     * Update a tenant's information.
     */
    public function update(UpdateTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $updated = $this->service->update($tenant->id, UpdateTenantData::from($request->validated()));

        return response()->json(['data' => $updated]);
    }

    /**
     * Set a tenant's active status.
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
     */
    public function destroy(Tenant $tenant): JsonResponse
    {
        $this->service->delete($tenant->id);

        return response()->json(['message' => 'Tenant deleted.']);
    }

    /**
     * Run migrations for a specific tenant.
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
