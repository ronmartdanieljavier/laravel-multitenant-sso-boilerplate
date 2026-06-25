<?php

namespace App\Admin\Tests;

use App\Admin\Data\CreateTenantData;
use App\Admin\Data\TenantData;
use App\Admin\Data\UpdateTenantData;
use App\Admin\Services\TenantManagementService;
use App\Models\Central\App;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantManagementServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private TenantManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TenantManagementService::class);
    }

    public function test_list_returns_collection_of_tenant_data(): void
    {
        $before = $this->service->list()->count();
        Tenant::factory()->count(3)->create();

        $result = $this->service->list();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount($before + 3, $result);
        $this->assertInstanceOf(TenantData::class, $result->first());
    }

    public function test_list_includes_health_and_credential_fields(): void
    {
        $slug = 'test-co-'.uniqid();
        Tenant::factory()->create(['slug' => $slug, 'db_host' => '10.0.0.1', 'db_name' => 'mydb', 'db_username' => 'admin']);

        $tenant = $this->service->list()->firstWhere('slug', $slug);

        $this->assertNotNull($tenant);
        $this->assertEquals('10.0.0.1', $tenant->dbHost);
        $this->assertEquals('mydb', $tenant->dbName);
        $this->assertEquals('admin', $tenant->dbUsername);
        $this->assertNotEmpty($tenant->healthStatus);
        $this->assertGreaterThanOrEqual(0, $tenant->userCount);
        $this->assertGreaterThanOrEqual(0, $tenant->migrationCount);
    }

    public function test_get_summary_returns_correct_counts(): void
    {
        $baseSummary = $this->service->getSummary($this->service->list());
        Tenant::factory()->create(['is_active' => true]);
        Tenant::factory()->create(['is_active' => false]);

        $tenants = $this->service->list();
        $summary = $this->service->getSummary($tenants);

        $this->assertEquals($baseSummary->total + 2, $summary->total);
        $this->assertGreaterThanOrEqual($baseSummary->critical + 1, $summary->critical);
    }

    public function test_create_stores_tenant_in_database(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('tenant:migrate', \Mockery::any());

        $data = new CreateTenantData(
            name: 'Service Corp',
            slug: 'service-corp',
            dbHost: '127.0.0.1',
            dbPort: 5432,
            dbName: 'tenant_service',
            dbUsername: 'user',
            dbPassword: 'pass',
            readReplicaHost: null,
            readReplicaPort: null,
            readReplicaUsername: null,
            readReplicaPassword: null,
        );

        $result = $this->service->create($data);

        $this->assertInstanceOf(TenantData::class, $result);
        $this->assertEquals('service-corp', $result->slug);
        $this->assertDatabaseHas('tenants', ['slug' => 'service-corp']);
    }

    public function test_update_modifies_tenant_fields(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Old Name']);

        $data = new UpdateTenantData(
            name: 'New Name',
            slug: $tenant->slug,
            dbHost: $tenant->db_host,
            dbPort: $tenant->db_port,
            dbName: $tenant->db_name,
            dbUsername: $tenant->db_username,
            dbPassword: null,
            readReplicaHost: null,
            readReplicaPort: null,
            readReplicaUsername: null,
            readReplicaPassword: null,
        );

        $result = $this->service->update($tenant->id, $data);

        $this->assertInstanceOf(TenantData::class, $result);
        $this->assertEquals('New Name', $result->name);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'name' => 'New Name']);
    }

    public function test_set_active_false_revokes_tenant_user_tokens(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $tenantUser = User::factory()->create();
        $tenantUser->createToken('my-token', ['*']);

        DB::table('user_app_tenants')->insert([
            'user_id' => $tenantUser->id,
            'app_id' => App::factory()->create()->id,
            'tenant_id' => $tenant->id,
            'role' => 'user',
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->service->setActive($tenant->id, false);

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'is_active' => false]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $tenantUser->id]);
    }

    public function test_set_active_true_does_not_affect_tokens(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => false]);
        $tenantUser = User::factory()->create();
        $tenantUser->createToken('my-token', ['*']);

        DB::table('user_app_tenants')->insert([
            'user_id' => $tenantUser->id,
            'app_id' => App::factory()->create()->id,
            'tenant_id' => $tenant->id,
            'role' => 'user',
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->service->setActive($tenant->id, true);

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'is_active' => true]);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $tenantUser->id]);
    }

    public function test_delete_removes_tenant_and_revokes_tokens(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantUser = User::factory()->create();
        $tenantUser->createToken('my-token', ['*']);

        DB::table('user_app_tenants')->insert([
            'user_id' => $tenantUser->id,
            'app_id' => App::factory()->create()->id,
            'tenant_id' => $tenant->id,
            'role' => 'user',
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->service->delete($tenant->id);

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $tenantUser->id]);
    }

    public function test_set_maintenance_true_revokes_tenant_user_tokens(): void
    {
        $tenant = Tenant::factory()->create(['is_maintenance' => false]);
        $tenantUser = User::factory()->create();
        $tenantUser->createToken('my-token', ['*']);

        DB::table('user_app_tenants')->insert([
            'user_id' => $tenantUser->id,
            'app_id' => App::factory()->create()->id,
            'tenant_id' => $tenant->id,
            'role' => 'user',
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->service->setMaintenance($tenant->id, true);

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'is_maintenance' => true]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $tenantUser->id]);
    }

    public function test_set_maintenance_false_does_not_affect_tokens(): void
    {
        $tenant = Tenant::factory()->create(['is_maintenance' => true]);
        $tenantUser = User::factory()->create();
        $tenantUser->createToken('my-token', ['*']);

        DB::table('user_app_tenants')->insert([
            'user_id' => $tenantUser->id,
            'app_id' => App::factory()->create()->id,
            'tenant_id' => $tenant->id,
            'role' => 'user',
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->service->setMaintenance($tenant->id, false);

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'is_maintenance' => false]);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $tenantUser->id]);
    }

    public function test_set_maintenance_all_updates_all_tenants(): void
    {
        $slug1 = 'svc-maint-a-'.uniqid();
        $slug2 = 'svc-maint-b-'.uniqid();
        Tenant::factory()->create(['slug' => $slug1, 'is_maintenance' => false]);
        Tenant::factory()->create(['slug' => $slug2, 'is_maintenance' => false]);

        $this->service->setMaintenanceAll(true);

        $this->assertDatabaseHas('tenants', ['slug' => $slug1, 'is_maintenance' => true]);
        $this->assertDatabaseHas('tenants', ['slug' => $slug2, 'is_maintenance' => true]);
    }
}
