<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Http\Requests\UpdateTenantSettingsRequest;
use App\Admin\Http\Requests\UploadTenantLogoRequest;
use App\Admin\Services\TenantSettingsService;
use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use Illuminate\Http\JsonResponse;

class TenantSettingsApiController extends Controller
{
    public function __construct(
        private readonly TenantSettingsService $service,
    ) {}

    /**
     * Get the tenant's settings (tenant overrides merged with system defaults).
     */
    public function index(Tenant $tenant): JsonResponse
    {
        $redisConnections = array_keys(array_filter(
            config('queue.connections', []),
            fn (array $c) => ($c['driver'] ?? '') === 'redis',
        ));

        return response()->json([
            'data' => $this->service->getSettings($tenant->id),
            'redis_connections' => $redisConnections,
        ]);
    }

    /**
     * Update the tenant's settings.
     */
    public function update(UpdateTenantSettingsRequest $request, Tenant $tenant): JsonResponse
    {
        $this->service->updateSettings($tenant->id, $request->validated());

        return response()->json(['data' => $this->service->getSettings($tenant->id)]);
    }

    /**
     * Upload the tenant's report logo.
     */
    public function uploadLogo(UploadTenantLogoRequest $request, Tenant $tenant): JsonResponse
    {
        $path = $this->service->uploadLogo($tenant->id, $request->file('logo'));

        return response()->json(['data' => ['report_logo_path' => $path]]);
    }

    /**
     * Delete the tenant's report logo.
     */
    public function deleteLogo(Tenant $tenant): JsonResponse
    {
        $this->service->deleteLogo($tenant->id);

        return response()->json(['message' => 'Logo removed.']);
    }
}
