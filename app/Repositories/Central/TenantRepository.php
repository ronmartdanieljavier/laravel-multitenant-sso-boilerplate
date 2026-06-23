<?php

namespace App\Repositories\Central;

use App\Models\Central\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;

class TenantRepository
{
    public function __construct(
        protected Tenant $model
    ) {}

    /**
     * Get all tenants with their migration versions eagerly loaded.
     *
     * @return Collection<int, Tenant>
     */
    public function allWithMigrationVersions(): Collection
    {
        return $this->model->with('migrationVersions')->get();
    }

    /**
     * Get a map of tenant_id => user count from the user_app_tenants pivot table.
     *
     * @return BaseCollection<int|string, int>
     */
    public function userCountByTenant(): BaseCollection
    {
        return DB::table('user_app_tenants')
            ->selectRaw('tenant_id, COUNT(DISTINCT user_id) as count')
            ->groupBy('tenant_id')
            ->pluck('count', 'tenant_id');
    }
}
