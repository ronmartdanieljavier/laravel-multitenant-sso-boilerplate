<?php

namespace App\Admin\Tests;

use App\Models\Central\App;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantUsersWebTest extends TestCase
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

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $tenant = Tenant::factory()->create();

        $this->get(route('admin.tenants.users', $tenant))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_tenant_users_page(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.tenants.users', $tenant))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Tenants/Users')
                ->has('tenant')
                ->has('users')
                ->has('apps')
                ->has('tenants')
            );
    }

    public function test_tenant_prop_contains_id_name_and_slug(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $tenant = Tenant::factory()->create(['name' => 'Acme Corp', 'slug' => 'acme']);

        $this->actingAs($admin)
            ->get(route('admin.tenants.users', $tenant))
            ->assertInertia(fn ($page) => $page
                ->where('tenant.id', $tenant->id)
                ->where('tenant.name', 'Acme Corp')
                ->where('tenant.slug', 'acme')
            );
    }

    public function test_users_prop_contains_only_users_assigned_to_tenant(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();

        $inTenant = User::factory()->create(['name' => 'In Tenant']);
        $notInTenant = User::factory()->create(['name' => 'Not In Tenant']);

        $this->assignUserToTenant($inTenant, $app, $tenant);
        $this->assignUserToTenant($notInTenant, $app, $otherTenant);

        $this->actingAs($admin)
            ->get(route('admin.tenants.users', $tenant))
            ->assertInertia(fn ($page) => $page
                ->where('users', fn ($users) => collect($users)->pluck('name')->contains('In Tenant'))
                ->where('users', fn ($users) => ! collect($users)->pluck('name')->contains('Not In Tenant'))
            );
    }

    public function test_user_data_contains_expected_fields(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenantUser = User::factory()->create();
        $this->assignUserToTenant($tenantUser, $app, $tenant);

        $this->actingAs($admin)
            ->get(route('admin.tenants.users', $tenant))
            ->assertInertia(fn ($page) => $page
                ->has('users.0.id')
                ->has('users.0.name')
                ->has('users.0.email')
                ->has('users.0.is_active')
                ->has('users.0.is_logged_in')
                ->has('users.0.apps')
            );
    }

    public function test_is_logged_in_is_true_when_user_has_active_token(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenantUser = User::factory()->create();
        $this->assignUserToTenant($tenantUser, $app, $tenant);
        $tenantUser->createToken('sso');

        $this->actingAs($admin)
            ->get(route('admin.tenants.users', $tenant))
            ->assertInertia(fn ($page) => $page
                ->where('users.0.is_logged_in', true)
            );
    }

    public function test_is_logged_in_is_false_when_user_has_no_token(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenantUser = User::factory()->create();
        $this->assignUserToTenant($tenantUser, $app, $tenant);

        $this->actingAs($admin)
            ->get(route('admin.tenants.users', $tenant))
            ->assertInertia(fn ($page) => $page
                ->where('users.0.is_logged_in', false)
            );
    }

    public function test_force_logout_revokes_user_tokens(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenantUser = User::factory()->create();
        $this->assignUserToTenant($tenantUser, $app, $tenant);
        $tenantUser->createToken('sso');

        $this->actingAs($admin)
            ->delete(route('admin.tenants.users.forceLogout', [$tenant, $tenantUser]))
            ->assertRedirect(route('admin.tenants.users', $tenant));

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $tenantUser->id]);
    }

    public function test_force_logout_unauthenticated_is_redirected(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        $this->delete(route('admin.tenants.users.forceLogout', [$tenant, $user]))->assertRedirect(route('login'));
    }

    public function test_users_prop_is_empty_when_no_users_assigned_to_tenant(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.tenants.users', $tenant))
            ->assertInertia(fn ($page) => $page->where('users', []));
    }

    public function test_returns_404_for_unknown_tenant(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());

        $this->actingAs($admin)
            ->get('/admin/tenants/99999/users')
            ->assertNotFound();
    }
}
