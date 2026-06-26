<?php

namespace App\Tenant\Tests;

use App\Auth\Enums\Role;
use App\Models\Central\App;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Tenant\Services\TenantSwitcherService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class TenantSwitcherApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private App $tenantApp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantApp = App::where('slug', 'tenant')->first() ?? App::factory()->create(['slug' => 'tenant']);
    }

    private function assignUserToTenant(User $user, Tenant $tenant, bool $isDefault = true): void
    {
        $user->userApps()->firstOrCreate(['app_id' => $this->tenantApp->id], ['role' => Role::User]);
        $user->userAppTenants()->create([
            'app_id' => $this->tenantApp->id,
            'tenant_id' => $tenant->id,
            'role' => Role::User,
            'is_default' => $isDefault,
        ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/tenant/tenants')->assertUnauthorized();
    }

    public function test_list_returns_assigned_tenants(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false]);
        $tenantB = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false]);

        $this->assignUserToTenant($user, $tenantA, true);
        $this->assignUserToTenant($user, $tenantB, false);

        Session::put(TenantSwitcherService::SESSION_KEY, $tenantA->slug);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/tenant/tenants')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', $tenantA->slug)
            ->assertJsonPath('data.1.slug', $tenantB->slug);
    }

    public function test_list_excludes_maintenance_tenants(): void
    {
        $user = User::factory()->create();
        $active = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false]);
        $maintenance = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => true]);

        $this->assignUserToTenant($user, $active, true);
        $this->assignUserToTenant($user, $maintenance, false);

        Session::put(TenantSwitcherService::SESSION_KEY, $active->slug);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/tenant/tenants')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_switch_updates_current_tenant(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false]);
        $tenantB = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false]);

        $this->assignUserToTenant($user, $tenantA, true);
        $this->assignUserToTenant($user, $tenantB, false);

        Session::put(TenantSwitcherService::SESSION_KEY, $tenantA->slug);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tenant/switch', ['tenant_slug' => $tenantB->slug])
            ->assertOk()
            ->assertJsonPath('message', 'Tenant switched successfully.');

        $this->assertSame($tenantB->slug, Session::get(TenantSwitcherService::SESSION_KEY));
    }

    public function test_switch_to_unassigned_tenant_returns_forbidden(): void
    {
        $user = User::factory()->create();
        $assigned = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false]);
        $other = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false]);

        $this->assignUserToTenant($user, $assigned);
        Session::put(TenantSwitcherService::SESSION_KEY, $assigned->slug);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tenant/switch', ['tenant_slug' => $other->slug])
            ->assertForbidden();
    }
}
