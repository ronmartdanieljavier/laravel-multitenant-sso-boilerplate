<?php

namespace App\Repositories\Central;

use App\Admin\Data\CreateTenantData;
use App\Admin\Data\UpdateTenantData;
use App\Data\Repositories\Central\TenantMigrationVersionRepositoryData;
use App\Data\Repositories\Central\TenantRepositoryData;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMigrationVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

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
     * Get a map of tenant_id => count of users with active tokens.
     *
     * @return BaseCollection<int|string, int>
     */
    public function loggedInUserCountByTenant(): BaseCollection
    {
        return DB::table('user_app_tenants')
            ->join('personal_access_tokens', function ($join) {
                $join->on('personal_access_tokens.tokenable_id', '=', 'user_app_tenants.user_id')
                    ->where('personal_access_tokens.tokenable_type', '=', 'App\\Models\\Central\\User');
            })
            ->selectRaw('user_app_tenants.tenant_id, COUNT(DISTINCT user_app_tenants.user_id) as count')
            ->groupBy('user_app_tenants.tenant_id')
            ->pluck('count', 'tenant_id');
    }

    /**
     * Get the IDs of users who belong to the given tenant and have active tokens.
     *
     * @return BaseCollection<int, int>
     */
    public function loggedInUserIdsForTenant(int $tenantId): BaseCollection
    {
        return DB::table('user_app_tenants')
            ->join('personal_access_tokens', function ($join) {
                $join->on('personal_access_tokens.tokenable_id', '=', 'user_app_tenants.user_id')
                    ->where('personal_access_tokens.tokenable_type', '=', 'App\\Models\\Central\\User');
            })
            ->where('user_app_tenants.tenant_id', $tenantId)
            ->distinct()
            ->pluck('user_app_tenants.user_id');
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

    public function countAvailableMigrations(): int
    {
        return count(File::files(database_path('migrations/tenant')));
    }

    public function countActive(): int
    {
        return $this->model->where('is_active', true)->count();
    }

    public function find(int $id): TenantRepositoryData
    {
        return $this->toData($this->model->findOrFail($id));
    }

    public function create(CreateTenantData $data): TenantRepositoryData
    {
        $tenant = $this->model->create([
            'name' => $data->name,
            'slug' => $data->slug,
            'db_host' => $data->dbHost,
            'db_port' => $data->dbPort,
            'db_name' => $data->dbName,
            'db_username' => $data->dbUsername,
            'db_password' => $data->dbPassword,
            'read_replica_host' => $data->readReplicaHost,
            'read_replica_port' => $data->readReplicaPort,
            'read_replica_username' => $data->readReplicaUsername,
            'read_replica_password' => $data->readReplicaPassword,
            'is_active' => true,
            'is_maintenance' => false,
        ]);

        return $this->toData($tenant);
    }

    public function update(int $id, UpdateTenantData $data): TenantRepositoryData
    {
        $tenant = $this->model->findOrFail($id);

        $updates = [
            'name' => $data->name,
            'slug' => $data->slug,
            'db_host' => $data->dbHost,
            'db_port' => $data->dbPort,
            'db_name' => $data->dbName,
            'db_username' => $data->dbUsername,
            'read_replica_host' => $data->readReplicaHost,
            'read_replica_port' => $data->readReplicaPort,
            'read_replica_username' => $data->readReplicaUsername,
        ];

        if ($data->dbPassword !== null) {
            $updates['db_password'] = $data->dbPassword;
        }

        if ($data->readReplicaPassword !== null) {
            $updates['read_replica_password'] = $data->readReplicaPassword;
        }

        $tenant->update($updates);

        return $this->toData($tenant->fresh());
    }

    public function setActive(int $id, bool $isActive): void
    {
        $this->model->findOrFail($id)->update(['is_active' => $isActive]);
    }

    public function setMaintenance(int $id, bool $isMaintenance): void
    {
        $this->model->findOrFail($id)->update(['is_maintenance' => $isMaintenance]);
    }

    public function setMaintenanceAll(bool $isMaintenance): void
    {
        $this->model->query()->update(['is_maintenance' => $isMaintenance]);
    }

    /**
     * @return BaseCollection<int, int>
     */
    public function getAllIds(): BaseCollection
    {
        return $this->model->pluck('id');
    }

    public function delete(int $id): void
    {
        $this->model->findOrFail($id)->delete();
    }

    public function dropDatabase(TenantRepositoryData $tenant): void
    {
        if ($tenant->dbName === null || $tenant->dbHost === null) {
            return;
        }

        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $tenant->dbName)) {
            return;
        }

        $config = [
            'driver' => 'pgsql',
            'host' => $tenant->dbHost,
            'port' => $tenant->dbPort ?? 5432,
            'database' => 'postgres',
            'username' => $tenant->dbUsername,
            'password' => $tenant->dbPassword,
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ];

        Config::set('database.connections.tenant_drop_admin', $config);
        DB::purge('tenant_drop_admin');

        try {
            DB::connection('tenant_drop_admin')
                ->statement("DROP DATABASE IF EXISTS \"{$tenant->dbName}\"");
        } catch (\Throwable) {
            // Best-effort: if the server is unreachable the central records are still removed.
        } finally {
            DB::purge('tenant_drop_admin');
            Config::set('database.connections.tenant_drop_admin', null);
        }
    }

    /**
     * @return BaseCollection<int, int>
     */
    public function getUserIdsForTenant(int $tenantId): BaseCollection
    {
        return DB::table('user_app_tenants')
            ->where('tenant_id', $tenantId)
            ->distinct()
            ->pluck('user_id');
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
            isMaintenance: $tenant->is_maintenance,
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
