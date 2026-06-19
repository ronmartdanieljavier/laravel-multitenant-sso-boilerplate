<?php

namespace App\Auth\Actions;

use App\Auth\Data\Core\AppAccessCoreData;
use App\Auth\Data\Core\TenantAccessCoreData;
use App\Auth\Models\User;
use App\Auth\Models\UserApp;
use App\Auth\Models\UserAppTenant;
use Spatie\LaravelData\DataCollection;

class LoadUserAppsAction
{
    public function execute(User $user): DataCollection
    {
        $userApps = $user->userApps()
            ->with('app')
            ->get();

        $tenantsByApp = $user->userAppTenants()
            ->with('tenant')
            ->get()
            ->groupBy('app_id');

        $apps = $userApps->map(function (UserApp $userApp) use ($tenantsByApp): AppAccessCoreData {
            $tenants = ($tenantsByApp->get($userApp->app_id) ?? collect())
                ->map(fn (UserAppTenant $uac): TenantAccessCoreData => new TenantAccessCoreData(
                    tenantId: $uac->tenant_id,
                    name: $uac->tenant->name,
                    slug: $uac->tenant->slug,
                    role: $uac->role,
                    isDefault: $uac->is_default,
                ))
                ->values()
                ->all();

            return new AppAccessCoreData(
                appId: $userApp->app_id,
                name: $userApp->app->name,
                slug: $userApp->app->slug,
                url: $userApp->app->url,
                role: $userApp->role,
                tenants: TenantAccessCoreData::collect($tenants, DataCollection::class),
            );
        })->all();

        return AppAccessCoreData::collect($apps, DataCollection::class);
    }
}
