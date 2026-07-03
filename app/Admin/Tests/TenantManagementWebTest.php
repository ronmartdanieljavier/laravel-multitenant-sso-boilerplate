<?php

namespace App\Admin\Tests;

use App\Models\Central\App;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantManagementWebTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('admin.tenants'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_tenant_management(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        Tenant::factory()->create(['name' => 'Acme Corp', 'slug' => 'acme']);

        $this->actingAs($user)
            ->get(route('admin.tenants'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Tenants/Index')
                ->has('tenants')
                ->has('summary')
            );
    }

    public function test_tenants_payload_includes_management_and_health_fields(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        Tenant::factory()->create(['slug' => 'acme']);

        $this->actingAs($user)
            ->get(route('admin.tenants'))
            ->assertInertia(fn ($page) => $page
                ->has('tenants.0', fn ($tenant) => $tenant
                    ->has('id')
                    ->has('name')
                    ->has('slug')
                    ->has('is_active')
                    ->has('db_host')
                    ->has('db_name')
                    ->has('db_username')
                    ->has('is_password_set')
                    ->has('has_read_replica')
                    ->has('user_count')
                    ->has('logged_in_count')
                    ->has('migration_count')
                    ->has('pending_reports')
                    ->has('failed_reports')
                    ->has('health_status')
                    ->etc()
                )
            );
    }

    public function test_summary_contains_all_status_counts(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());

        $this->actingAs($user)
            ->get(route('admin.tenants'))
            ->assertInertia(fn ($page) => $page
                ->has('summary', fn ($summary) => $summary
                    ->has('total')
                    ->has('healthy')
                    ->has('warning')
                    ->has('critical')
                    ->has('maintenance')
                )
            );
    }

    public function test_authenticated_user_can_create_tenant(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());

        $this->actingAs($user)
            ->post(route('admin.tenants.store'), [
                'name' => 'New Corp',
                'slug' => 'new-corp',
                'db_host' => '127.0.0.1',
                'db_port' => 5432,
                'db_name' => 'tenant_new_corp',
                'db_username' => 'dbuser',
                'db_password' => 'secret',
            ])
            ->assertRedirect(route('admin.tenants'));

        $this->assertDatabaseHas('tenants', ['slug' => 'new-corp', 'name' => 'New Corp']);
    }

    public function test_create_rejects_duplicate_slug(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        Tenant::factory()->create(['slug' => 'taken']);

        $this->actingAs($user)
            ->post(route('admin.tenants.store'), [
                'name' => 'Taken',
                'slug' => 'taken',
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_authenticated_user_can_update_tenant(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        $tenant = Tenant::factory()->create(['name' => 'Old Name', 'slug' => 'old-slug']);

        $this->actingAs($user)
            ->put(route('admin.tenants.update', $tenant), [
                'name' => 'New Name',
                'slug' => 'new-slug',
                'db_host' => $tenant->db_host,
                'db_port' => $tenant->db_port,
                'db_name' => $tenant->db_name,
                'db_username' => $tenant->db_username,
            ])
            ->assertRedirect(route('admin.tenants'));

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'name' => 'New Name', 'slug' => 'new-slug']);
    }

    public function test_update_slug_unique_excludes_self(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        $tenant = Tenant::factory()->create(['slug' => 'my-slug']);

        $this->actingAs($user)
            ->put(route('admin.tenants.update', $tenant), [
                'name' => 'Updated Name',
                'slug' => 'my-slug',
                'db_host' => $tenant->db_host,
                'db_port' => $tenant->db_port,
                'db_name' => $tenant->db_name,
                'db_username' => $tenant->db_username,
            ])
            ->assertRedirect(route('admin.tenants'));
    }

    public function test_authenticated_user_can_deactivate_tenant_and_tokens_are_revoked(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
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

        $this->actingAs($admin)
            ->patch(route('admin.tenants.setActive', $tenant), ['is_active' => false])
            ->assertRedirect(route('admin.tenants'));

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'is_active' => false]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $tenantUser->id]);
    }

    public function test_authenticated_user_can_activate_tenant(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $tenant = Tenant::factory()->create(['is_active' => false]);

        $this->actingAs($admin)
            ->patch(route('admin.tenants.setActive', $tenant), ['is_active' => true])
            ->assertRedirect(route('admin.tenants'));

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'is_active' => true]);
    }

    public function test_authenticated_user_can_delete_tenant(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.tenants.destroy', $tenant))
            ->assertRedirect(route('admin.tenants'));

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
    }

    public function test_deleting_tenant_also_revokes_user_tokens(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $tenant = Tenant::factory()->create();
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

        $this->actingAs($admin)
            ->delete(route('admin.tenants.destroy', $tenant))
            ->assertRedirect(route('admin.tenants'));

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $tenantUser->id]);
    }

    public function test_logged_in_count_reflects_users_with_active_tokens(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'online-test-'.uniqid()]);

        $userWithToken = User::factory()->create();
        $userWithoutToken = User::factory()->create();

        foreach ([$userWithToken, $userWithoutToken] as $u) {
            DB::table('user_app_tenants')->insert([
                'user_id' => $u->id,
                'app_id' => $app->id,
                'tenant_id' => $tenant->id,
                'role' => 'user',
                'is_default' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $userWithToken->createToken('sso');

        $this->actingAs($admin)
            ->get(route('admin.tenants'))
            ->assertInertia(fn ($page) => $page
                ->where('tenants', fn ($tenants) => collect($tenants)
                    ->firstWhere('id', $tenant->id)['logged_in_count'] === 1
                )
            );
    }

    public function test_tenants_payload_includes_is_maintenance_field(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        Tenant::factory()->create(['slug' => 'maint-test-'.uniqid(), 'is_maintenance' => false]);

        $this->actingAs($user)
            ->get(route('admin.tenants'))
            ->assertInertia(fn ($page) => $page
                ->has('tenants.0', fn ($tenant) => $tenant
                    ->has('is_maintenance')
                    ->etc()
                )
            );
    }

    public function test_authenticated_user_can_enable_maintenance_mode_and_tokens_are_revoked(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $tenant = Tenant::factory()->create(['is_maintenance' => false]);
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

        $this->actingAs($admin)
            ->patch(route('admin.tenants.setMaintenance', $tenant), ['is_maintenance' => true])
            ->assertRedirect(route('admin.tenants'));

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'is_maintenance' => true]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $tenantUser->id]);
    }

    public function test_authenticated_user_can_disable_maintenance_mode(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $tenant = Tenant::factory()->create(['is_maintenance' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.tenants.setMaintenance', $tenant), ['is_maintenance' => false])
            ->assertRedirect(route('admin.tenants'));

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'is_maintenance' => false]);
    }

    public function test_authenticated_user_can_enable_maintenance_for_all_tenants(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $slug1 = 'maint-all-a-'.uniqid();
        $slug2 = 'maint-all-b-'.uniqid();
        Tenant::factory()->create(['slug' => $slug1, 'is_maintenance' => false]);
        Tenant::factory()->create(['slug' => $slug2, 'is_maintenance' => false]);

        $this->actingAs($admin)
            ->patch(route('admin.tenants.setMaintenanceAll'), ['is_maintenance' => true])
            ->assertRedirect(route('admin.tenants'));

        $this->assertDatabaseHas('tenants', ['slug' => $slug1, 'is_maintenance' => true]);
        $this->assertDatabaseHas('tenants', ['slug' => $slug2, 'is_maintenance' => true]);
    }

    public function test_authenticated_user_can_disable_maintenance_for_all_tenants(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $slug1 = 'maint-off-a-'.uniqid();
        $slug2 = 'maint-off-b-'.uniqid();
        Tenant::factory()->create(['slug' => $slug1, 'is_maintenance' => true]);
        Tenant::factory()->create(['slug' => $slug2, 'is_maintenance' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.tenants.setMaintenanceAll'), ['is_maintenance' => false])
            ->assertRedirect(route('admin.tenants'));

        $this->assertDatabaseHas('tenants', ['slug' => $slug1, 'is_maintenance' => false]);
        $this->assertDatabaseHas('tenants', ['slug' => $slug2, 'is_maintenance' => false]);
    }

    public function test_inactive_tenant_appears_as_critical_in_response(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        Tenant::factory()->create(['is_active' => false, 'slug' => 'inactive-co']);

        $this->actingAs($user)
            ->get(route('admin.tenants'))
            ->assertInertia(fn ($page) => $page
                ->where('tenants', fn ($tenants) => collect($tenants)
                    ->firstWhere('slug', 'inactive-co')['health_status'] === 'critical'
                )
            );
    }
}
