<?php

namespace Tests\Feature\Repositories\Central;

use App\Data\Repositories\Central\TenantMigrationVersionRepositoryData;
use App\Data\Repositories\Central\TenantRepositoryData;
use App\Models\Central\Tenant;
use App\Repositories\Central\TenantRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TenantRepositoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private TenantRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(TenantRepository::class);
    }

    public function test_list_ordered_returns_collection_of_tenant_repository_data(): void
    {
        Tenant::factory()->create();

        $results = $this->repository->listOrdered();

        $this->assertNotEmpty($results);
        $this->assertInstanceOf(TenantRepositoryData::class, $results->first());
    }

    public function test_list_ordered_dto_contains_db_config_fields(): void
    {
        $tenant = Tenant::factory()->create([
            'db_host' => '10.0.0.1',
            'db_port' => 5432,
            'db_name' => 'mydb',
            'db_username' => 'root',
        ]);

        $dto = $this->repository->listOrdered()->firstWhere('id', $tenant->id);

        $this->assertSame('10.0.0.1', $dto->dbHost);
        $this->assertSame(5432, $dto->dbPort);
        $this->assertSame('mydb', $dto->dbName);
        $this->assertSame('root', $dto->dbUsername);
    }

    public function test_list_ordered_has_read_replica_false_when_no_replica(): void
    {
        $tenant = Tenant::factory()->create(['read_replica_host' => null]);

        $dto = $this->repository->listOrdered()->firstWhere('id', $tenant->id);

        $this->assertFalse($dto->hasReadReplica);
    }

    public function test_list_ordered_has_read_replica_true_when_replica_configured(): void
    {
        $tenant = Tenant::factory()->create(['read_replica_host' => '10.0.0.2']);

        $dto = $this->repository->listOrdered()->firstWhere('id', $tenant->id);

        $this->assertTrue($dto->hasReadReplica);
    }

    public function test_list_ordered_exposes_read_replica_connection_fields(): void
    {
        $tenant = Tenant::factory()->create([
            'read_replica_host' => 'replica.example.com',
            'read_replica_port' => 5433,
            'read_replica_username' => 'replica_user',
        ]);

        $dto = $this->repository->listOrdered()->firstWhere('id', $tenant->id);

        $this->assertSame('replica.example.com', $dto->readReplicaHost);
        $this->assertSame(5433, $dto->readReplicaPort);
        $this->assertSame('replica_user', $dto->readReplicaUsername);
    }

    public function test_list_ordered_read_replica_fields_null_when_no_replica(): void
    {
        $tenant = Tenant::factory()->create(['read_replica_host' => null]);

        $dto = $this->repository->listOrdered()->firstWhere('id', $tenant->id);

        $this->assertNull($dto->readReplicaHost);
        $this->assertNull($dto->readReplicaUsername);
    }

    public function test_all_with_migration_versions_returns_tenants_with_embedded_versions(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->migrationVersions()->create([
            'migration' => '2024_01_01_000001_create_users_table',
            'batch' => 1,
            'migrated_at' => Carbon::parse('2024-01-01 10:00:00'),
        ]);

        $results = $this->repository->allWithMigrationVersions();
        $dto = $results->firstWhere('id', $tenant->id);

        $this->assertInstanceOf(TenantRepositoryData::class, $dto);
        $this->assertCount(1, $dto->migrationVersions);
        $this->assertInstanceOf(TenantMigrationVersionRepositoryData::class, $dto->migrationVersions[0]);
        $this->assertSame('2024_01_01_000001_create_users_table', $dto->migrationVersions[0]->migration);
        $this->assertSame(1, $dto->migrationVersions[0]->batch);
    }

    public function test_all_with_migration_versions_returns_empty_array_when_no_versions(): void
    {
        $tenant = Tenant::factory()->create();

        $dto = $this->repository->allWithMigrationVersions()->firstWhere('id', $tenant->id);

        $this->assertSame([], $dto->migrationVersions);
    }

    public function test_list_active_returns_only_active_tenants(): void
    {
        $inactive = Tenant::factory()->create(['is_active' => false, 'slug' => 'inactive-tenant-xyz']);

        $results = $this->repository->listActive();

        $inactiveSlugs = $results->pluck('slug');
        $this->assertFalse($inactiveSlugs->contains('inactive-tenant-xyz'));
        foreach ($results as $dto) {
            $this->assertTrue($dto->isActive);
        }
    }

    public function test_list_active_filters_by_slug(): void
    {
        Tenant::factory()->create(['slug' => 'acme', 'is_active' => true]);
        Tenant::factory()->create(['slug' => 'other', 'is_active' => true]);

        $results = $this->repository->listActive('acme');

        $this->assertCount(1, $results);
        $this->assertSame('acme', $results->first()->slug);
    }
}
