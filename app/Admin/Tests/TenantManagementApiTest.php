<?php

namespace App\Admin\Tests;

use App\Models\Central\App;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantManagementApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/admin/tenants')->assertUnauthorized();
    }

    public function test_authenticated_user_can_list_tenants(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Tenant::factory()->count(2)->create();

        $this->getJson('/api/v1/admin/tenants')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'is_active', 'health_status'],
                ],
                'summary' => ['total', 'healthy', 'warning', 'critical'],
            ]);
    }

    public function test_authenticated_user_can_create_tenant(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/admin/tenants', [
            'name' => 'API Corp',
            'slug' => 'api-corp',
            'db_host' => '127.0.0.1',
            'db_port' => 5432,
            'db_name' => 'tenant_api_corp',
            'db_username' => 'apiuser',
            'db_password' => 'secret',
        ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'api-corp');

        $this->assertDatabaseHas('tenants', ['slug' => 'api-corp']);
    }

    public function test_create_rejects_duplicate_slug(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Tenant::factory()->create(['slug' => 'taken']);

        $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Taken',
            'slug' => 'taken',
        ])->assertUnprocessable();
    }

    public function test_authenticated_user_can_update_tenant(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $tenant = Tenant::factory()->create(['name' => 'Before']);

        $this->putJson("/api/v1/admin/tenants/{$tenant->id}", [
            'name' => 'After',
            'slug' => $tenant->slug,
            'db_host' => $tenant->db_host,
            'db_port' => $tenant->db_port,
            'db_name' => $tenant->db_name,
            'db_username' => $tenant->db_username,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'After');

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'name' => 'After']);
    }

    public function test_authenticated_user_can_deactivate_tenant(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $tenant = Tenant::factory()->create(['is_active' => true]);

        $this->patchJson("/api/v1/admin/tenants/{$tenant->id}/active", ['is_active' => false])
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'is_active' => false]);
    }

    public function test_deactivating_tenant_revokes_user_tokens(): void
    {
        $admin = User::factory()->create();
        Sanctum::actingAs($admin);

        $tenant = Tenant::factory()->create(['is_active' => true]);
        $tenantUser = User::factory()->create();
        $tenantUser->createToken('test-token', ['*']);

        DB::table('user_app_tenants')->insert([
            'user_id' => $tenantUser->id,
            'app_id' => App::factory()->create()->id,
            'tenant_id' => $tenant->id,
            'role' => 'user',
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->patchJson("/api/v1/admin/tenants/{$tenant->id}/active", ['is_active' => false])
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $tenantUser->id]);
    }

    public function test_authenticated_user_can_delete_tenant(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $tenant = Tenant::factory()->create();

        $this->deleteJson("/api/v1/admin/tenants/{$tenant->id}")
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
    }

    public function test_delete_returns_404_for_missing_tenant(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/v1/admin/tenants/99999')->assertNotFound();
    }

    public function test_update_returns_422_for_duplicate_slug(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Tenant::factory()->create(['slug' => 'other']);
        $tenant = Tenant::factory()->create(['slug' => 'mine']);

        $this->putJson("/api/v1/admin/tenants/{$tenant->id}", [
            'name' => $tenant->name,
            'slug' => 'other',
        ])->assertUnprocessable();
    }

    public function test_authenticated_user_can_enable_maintenance_mode(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $tenant = Tenant::factory()->create(['is_maintenance' => false]);

        $this->patchJson("/api/v1/admin/tenants/{$tenant->id}/maintenance", ['is_maintenance' => true])
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'is_maintenance' => true]);
    }

    public function test_enabling_maintenance_mode_revokes_user_tokens(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $tenant = Tenant::factory()->create(['is_maintenance' => false]);
        $tenantUser = User::factory()->create();
        $tenantUser->createToken('api-token', ['*']);

        DB::table('user_app_tenants')->insert([
            'user_id' => $tenantUser->id,
            'app_id' => App::factory()->create()->id,
            'tenant_id' => $tenant->id,
            'role' => 'user',
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->patchJson("/api/v1/admin/tenants/{$tenant->id}/maintenance", ['is_maintenance' => true])
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $tenantUser->id]);
    }

    public function test_authenticated_user_can_disable_maintenance_mode(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $tenant = Tenant::factory()->create(['is_maintenance' => true]);

        $this->patchJson("/api/v1/admin/tenants/{$tenant->id}/maintenance", ['is_maintenance' => false])
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'is_maintenance' => false]);
    }

    public function test_authenticated_user_can_enable_maintenance_for_all_tenants(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $slug1 = 'api-maint-a-'.uniqid();
        $slug2 = 'api-maint-b-'.uniqid();
        Tenant::factory()->create(['slug' => $slug1, 'is_maintenance' => false]);
        Tenant::factory()->create(['slug' => $slug2, 'is_maintenance' => false]);

        $this->patchJson('/api/v1/admin/tenants/maintenance/all', ['is_maintenance' => true])
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('tenants', ['slug' => $slug1, 'is_maintenance' => true]);
        $this->assertDatabaseHas('tenants', ['slug' => $slug2, 'is_maintenance' => true]);
    }
}
