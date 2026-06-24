<?php

namespace App\Repositories\Central;

use App\Admin\Data\UserAppPermissionData;
use App\Auth\Enums\Role;
use App\Models\Central\User;
use App\Models\Central\UserApp;
use App\Models\Central\UserAppTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Spatie\LaravelData\DataCollection;

class UserAppRepository
{
    public function __construct(
        protected User $model
    ) {}

    /**
     * Get all user apps with their app relationship for a given user ID.
     *
     * @return Collection<int, UserApp>
     */
    public function getAppsForUser(int $userId): Collection
    {
        $user = $this->model->findOrFail($userId);

        return $user->userApps()->with('app')->get();
    }

    /**
     * Get user app tenants grouped by app_id for a given user ID.
     *
     * @return BaseCollection<int|string, mixed>
     */
    public function getTenantsByAppForUser(int $userId): BaseCollection
    {
        $user = $this->model->findOrFail($userId);

        return $user->userAppTenants()->with('tenant')->get()->groupBy('app_id');
    }

    /**
     * Sync a user's app and tenant permissions to exactly match the given collection.
     * Apps not in the collection are removed; tenant assignments per app are also synced.
     *
     * @param  DataCollection<int, UserAppPermissionData>  $apps
     */
    public function syncPermissions(int $userId, DataCollection $apps): void
    {
        $incomingAppIds = $apps->toCollection()->pluck('appId')->all();

        UserApp::where('user_id', $userId)
            ->whereNotIn('app_id', $incomingAppIds)
            ->delete();

        UserAppTenant::where('user_id', $userId)
            ->whereNotIn('app_id', $incomingAppIds)
            ->delete();

        foreach ($apps as $appPermission) {
            $role = Role::from($appPermission->role);

            UserApp::updateOrCreate(
                ['user_id' => $userId, 'app_id' => $appPermission->appId],
                ['role' => $role],
            );

            UserAppTenant::where('user_id', $userId)
                ->where('app_id', $appPermission->appId)
                ->whereNotIn('tenant_id', $appPermission->tenantIds)
                ->delete();

            foreach ($appPermission->tenantIds as $index => $tenantId) {
                UserAppTenant::updateOrCreate(
                    ['user_id' => $userId, 'app_id' => $appPermission->appId, 'tenant_id' => $tenantId],
                    ['role' => $role, 'is_default' => $index === 0],
                );
            }
        }
    }
}
