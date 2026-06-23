<?php

namespace App\Repositories\Central;

use App\Models\Central\User;
use App\Models\Central\UserApp;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

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
}
