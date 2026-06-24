<?php

namespace App\Repositories\Central;

use App\Data\Repositories\Central\TenantMigrationVersionRepositoryData;
use App\Data\Repositories\Central\TenantRepositoryData;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMigrationVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;

class TenantRepository
{
    public function __construct(
        protected Tenant $model
    ) {}

    /**
     * @return Collection<int, TenantRepositoryData>
     */
    public function allWithMigrationVersions(): Collection
    {
        return $this->model->with('migrationVersions')->get()
            ->map(fn (Tenant $tenant) => $this->toData($tenant, withVersions: true));
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

    /**
     * @return Collection<int, TenantRepositoryData>
     */
    public function listActiveWithMigrationVersions(?string $slug = null): Collection
    {
        $query = $this->model->with('migrationVersions')->where('is_active', true);

        if ($slug !== null) {
            $query->where('slug', $slug);
        }

        return $query->get()->map(fn (Tenant $tenant) => $this->toData($tenant, withVersions: true));
    }

    /**
     * @return Collection<int, TenantRepositoryData>
     */
    public function listActive(?string $slug = null): Collection
    {
        $query = $this->model->where('is_active', true);

        if ($slug !== null) {
            $query->where('slug', $slug);
        }

        return $query->get()->map(fn (Tenant $tenant) => $this->toData($tenant));
    }

    /**
     * @return Collection<int, TenantRepositoryData>
     */
    public function listOrdered(): Collection
    {
        return $this->model->orderBy('name')->get()
            ->map(fn (Tenant $tenant) => $this->toData($tenant));
    }

    public function syncMigrationVersion(int $tenantId, string $migration, int $batch): void
    {
        TenantMigrationVersion::updateOrCreate(
            ['tenant_id' => $tenantId, 'migration' => $migration],
            ['batch' => $batch, 'migrated_at' => now()],
        );
    }

    private function toData(Tenant $tenant, bool $withVersions = false): TenantRepositoryData
    {
        $migrationVersions = $withVersions
            ? $tenant->migrationVersions->map(
                fn (TenantMigrationVersion $v) => new TenantMigrationVersionRepositoryData(
                    migration: $v->migration,
                    batch: $v->batch,
                    migratedAt: $v->migrated_at,
                )
            )->all()
            : [];

        return new TenantRepositoryData(
            id: $tenant->id,
            name: $tenant->name,
            slug: $tenant->slug,
            isActive: $tenant->is_active,
            dbHost: $tenant->db_host,
            dbPort: $tenant->db_port,
            dbName: $tenant->db_name,
            dbUsername: $tenant->db_username,
            dbPassword: $tenant->db_password,
            hasReadReplica: $tenant->hasReadReplica(),
            migrationVersions: $migrationVersions,
        );
    }
}
