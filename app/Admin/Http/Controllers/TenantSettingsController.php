<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Http\Requests\UpdateTenantSettingsRequest;
use App\Admin\Http\Requests\UploadTenantLogoRequest;
use App\Admin\Services\TenantSettingsService;
use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TenantSettingsController extends Controller
{
    public function __construct(
        private readonly TenantSettingsService $service,
    ) {}

    /**
     * Display the tenant's settings page.
     *
     * @param  Tenant  $tenant  the tenant for which to display settings
     */
    public function index(Tenant $tenant): Response
    {
        $redisConnections = array_keys(array_filter(
            config('queue.connections', []),
            fn (array $c) => ($c['driver'] ?? '') === 'redis',
        ));

        return Inertia::render('Admin/Tenants/Settings', [
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug],
            'settings' => $this->service->getSettings($tenant->id),
            'redisConnections' => $redisConnections,
        ]);
    }

    /**
     * Update the tenant's settings.
     *
     * @param  UpdateTenantSettingsRequest  $request  validated request data
     * @param  Tenant  $tenant  the tenant for which to update settings
     */
    public function update(UpdateTenantSettingsRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->service->updateSettings($tenant->id, $request->validated());

        return redirect()->route('admin.tenants.settings', $tenant)->with('success', 'Tenant settings saved.');
    }

    /**
     * Upload the tenant's report logo.
     *
     * @param  UploadTenantLogoRequest  $request  validated request data
     * @param  Tenant  $tenant  the tenant for which to upload the logo
     */
    public function uploadLogo(UploadTenantLogoRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->service->uploadLogo($tenant->id, $request->file('logo'));

        return redirect()->route('admin.tenants.settings', $tenant)->with('success', 'Logo uploaded.');
    }

    /**
     * Delete the tenant's report logo.
     *
     * @param  Tenant  $tenant  the tenant for which to delete the logo
     */
    public function deleteLogo(Tenant $tenant): RedirectResponse
    {
        $this->service->deleteLogo($tenant->id);

        return redirect()->route('admin.tenants.settings', $tenant)->with('success', 'Logo removed.');
    }
}
