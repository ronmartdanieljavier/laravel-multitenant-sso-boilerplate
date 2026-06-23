<?php

namespace App\Auth\Services;

use App\Auth\Data\AppAccessData;
use App\Auth\Data\TenantAccessData;
use App\Models\Central\UserApp;
use App\Models\Central\UserAppTenant;
use App\Repositories\Central\UserAppRepository;
use Spatie\LaravelData\DataCollection;

class AppService
{
    public function __construct(
        protected UserAppRepository $userAppRepository
    ) {}

    /**
     * Load all apps and tenant access for a given user ID.
     *
     * @return DataCollection<int, AppAccessData>
     */
    public function loadApps(int $userId): DataCollection
    {
        $userApps = $this->userAppRepository->getAppsForUser($userId);
        $tenantsByApp = $this->userAppRepository->getTenantsByAppForUser($userId);

        $apps = $userApps->map(function (UserApp $userApp) use ($tenantsByApp): AppAccessData {
            $tenants = ($tenantsByApp->get($userApp->app_id) ?? collect())
                ->map(fn (UserAppTenant $uac): TenantAccessData => new TenantAccessData(
                    tenantId: $uac->tenant_id,
                    name: $uac->tenant->name,
                    slug: $uac->tenant->slug,
                    role: $uac->role,
                    isDefault: $uac->is_default,
                ))
                ->values()
                ->all();

            return new AppAccessData(
                appId: $userApp->app_id,
                name: $userApp->app->name,
                slug: $userApp->app->slug,
                url: $userApp->app->url,
                description: $userApp->app->description ?? null,
                role: $userApp->role,
                tenants: TenantAccessData::collect($tenants, DataCollection::class),
            );
        })->all();

        return AppAccessData::collect($apps, DataCollection::class);
    }
}
