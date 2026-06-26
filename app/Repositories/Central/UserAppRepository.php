<?php

namespace App\Repositories\Central;

use App\Admin\Data\UserAppPermissionData;
use App\Auth\Enums\Role;
use App\Data\Repositories\Central\TenantRepositoryData;
use App\Models\Central\App;
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
     * Get tenants assigned to a user for a specific app slug, excluding maintenance tenants.
     *
     * @return BaseCollection<int, TenantRepositoryData>
     */
    public function getTenantsForUserAndApp(int $userId, string $appSlug): BaseCollection
    {
        $app = App::where('slug', $appSlug)->firstOrFail();

        return UserAppTenant::where('user_id', $userId)
            ->where('app_id', $app->id)
            ->with('tenant')
            ->get()
            ->filter(fn (UserAppTenant $uat): bool => $uat->tenant->is_active && ! $uat->tenant->is_maintenance)
            ->map(fn (UserAppTenant $uat): TenantRepositoryData => new TenantRepositoryData(
                id: $uat->tenant->id,
                name: $uat->tenant->name,
                slug: $uat->tenant->slug,
                isActive: $uat->tenant->is_active,
                isMaintenance: $uat->tenant->is_maintenance,
                dbHost: $uat->tenant->db_host,
                dbPort: $uat->tenant->db_port,
                dbName: $uat->tenant->db_name,
                dbUsername: $uat->tenant->db_username,
                dbPassword: $uat->tenant->db_password,
                hasReadReplica: $uat->tenant->hasReadReplica(),
            ))
            ->values();
    }

    /**
     * Get the default tenant slug for a user+app combination.
     */
    public function getDefaultTenantSlugForUserAndApp(int $userId, string $appSlug): ?string
    {
        $app = App::where('slug', $appSlug)->first();

        if (! $app) {
            return null;
        }

        $uat = UserAppTenant::where('user_id', $userId)
            ->where('app_id', $app->id)
            ->where('is_default', true)
            ->with('tenant')
            ->first();

        if (! $uat) {
            $uat = UserAppTenant::where('user_id', $userId)
                ->where('app_id', $app->id)
                ->with('tenant')
                ->first();
        }

        return $uat?->tenant?->slug;
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
