<?php

namespace App\Login\Actions;

use App\Login\Data\Core\AppAccessCoreData;
use App\Login\Data\Core\ClientAccessCoreData;
use App\Login\Models\User;
use App\Login\Models\UserApp;
use App\Login\Models\UserAppClient;
use Spatie\LaravelData\DataCollection;

class LoadUserAppsAction
{
    public function execute(User $user): DataCollection
    {
        $userApps = $user->userApps()
            ->with('app')
            ->get();

        $clientsByApp = $user->userAppClients()
            ->with('client')
            ->get()
            ->groupBy('app_id');

        $apps = $userApps->map(function (UserApp $userApp) use ($clientsByApp): AppAccessCoreData {
            $clients = ($clientsByApp->get($userApp->app_id) ?? collect())
                ->map(fn (UserAppClient $uac): ClientAccessCoreData => new ClientAccessCoreData(
                    clientId: $uac->client_id,
                    name: $uac->client->name,
                    slug: $uac->client->slug,
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
                clients: ClientAccessCoreData::collect($clients, DataCollection::class),
            );
        })->all();

        return AppAccessCoreData::collect($apps, DataCollection::class);
    }
}
