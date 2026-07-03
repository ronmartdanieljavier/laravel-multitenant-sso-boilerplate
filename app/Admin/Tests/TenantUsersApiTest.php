<?php

namespace App\Admin\Tests;

use App\Models\Central\App;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantUsersApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function assignUserToTenant(User $user, App $app, Tenant $tenant, string $role = 'user'): void
    {
        DB::table('user_app_tenants')->insert([
            'user_id' => $user->id,
            'app_id' => $app->id,
            'tenant_id' => $tenant->id,
            'role' => $role,
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();

        $this->getJson(route('admin.api.tenants.users', $tenant))->assertUnauthorized();
    }

    public function test_authenticated_user_can_list_tenant_users(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson(route('admin.api.tenants.users', $tenant))
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_response_contains_users_assigned_to_tenant(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenantUser = User::factory()->create(['name' => 'Scoped User']);

        $this->assignUserToTenant($tenantUser, $app, $tenant);

        $data = $this->actingAs($admin, 'sanctum')
            ->getJson(route('admin.api.tenants.users', $tenant))
            ->assertOk()
            ->json('data');

        $this->assertTrue(collect($data)->pluck('name')->contains('Scoped User'));
    }

    public function test_response_excludes_users_not_assigned_to_tenant(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['name' => 'Other Tenant User']);

        $this->assignUserToTenant($otherUser, $app, $otherTenant);

        $data = $this->actingAs($admin, 'sanctum')
            ->getJson(route('admin.api.tenants.users', $tenant))
            ->assertOk()
            ->json('data');

        $this->assertFalse(collect($data)->pluck('name')->contains('Other Tenant User'));
    }

    public function test_each_user_has_expected_fields(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenantUser = User::factory()->create();

        $this->assignUserToTenant($tenantUser, $app, $tenant);

        $this->actingAs($admin, 'sanctum')
            ->getJson(route('admin.api.tenants.users', $tenant))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'is_active', 'is_logged_in', 'apps'],
                ],
            ]);
    }

    public function test_is_logged_in_is_true_when_user_has_active_token(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenantUser = User::factory()->create();

        $this->assignUserToTenant($tenantUser, $app, $tenant);
        $tenantUser->createToken('sso');

        $data = $this->actingAs($admin, 'sanctum')
            ->getJson(route('admin.api.tenants.users', $tenant))
            ->assertOk()
            ->json('data');

        $user = collect($data)->firstWhere('id', $tenantUser->id);
        $this->assertTrue($user['is_logged_in']);
    }

    public function test_is_logged_in_is_false_when_user_has_no_active_token(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenantUser = User::factory()->create();

        $this->assignUserToTenant($tenantUser, $app, $tenant);

        $data = $this->actingAs($admin, 'sanctum')
            ->getJson(route('admin.api.tenants.users', $tenant))
            ->assertOk()
            ->json('data');

        $user = collect($data)->firstWhere('id', $tenantUser->id);
        $this->assertFalse($user['is_logged_in']);
    }

    public function test_force_logout_revokes_user_tokens(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenantUser = User::factory()->create();

        $this->assignUserToTenant($tenantUser, $app, $tenant);
        $tenantUser->createToken('sso');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson(route('admin.api.tenants.users.forceLogout', [$tenant, $tenantUser]))
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $tenantUser->id]);
    }

    public function test_force_logout_unauthenticated_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        $this->deleteJson(route('admin.api.tenants.users.forceLogout', [$tenant, $user]))->assertUnauthorized();
    }

    public function test_returns_empty_data_when_no_users_in_tenant(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson(route('admin.api.tenants.users', $tenant))
            ->assertOk()
            ->assertJson(['data' => []]);
    }

    public function test_returns_404_for_unknown_tenant(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/tenants/99999/users')
            ->assertNotFound();
    }
}
