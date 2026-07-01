<?php

namespace App\Tenant\Tests;

use App\Auth\Enums\Role;
use App\Models\Central\App;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Tenant\Data\TenantData;
use App\Tenant\Services\TenantSwitcherService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class TenantSwitcherServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private TenantSwitcherService $service;

    private App $tenantApp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TenantSwitcherService::class);
        $this->tenantApp = App::where('slug', 'tenant')->first() ?? App::factory()->create(['slug' => 'tenant']);
    }

    public function test_get_tenants_for_user_returns_assigned_tenants(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false]);
        $tenantB = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false]);

        $user->userApps()->create(['app_id' => $this->tenantApp->id, 'role' => Role::User]);
        $user->userAppTenants()->create(['app_id' => $this->tenantApp->id, 'tenant_id' => $tenantA->id, 'role' => Role::User, 'is_default' => true]);
        $user->userAppTenants()->create(['app_id' => $this->tenantApp->id, 'tenant_id' => $tenantB->id, 'role' => Role::User, 'is_default' => false]);

        $tenants = $this->service->getTenantsForUser($user->id, 'tenant');

        $this->assertCount(2, $tenants);
        $this->assertTrue($tenants->contains(fn (TenantData $t) => $t->slug === $tenantA->slug));
        $this->assertTrue($tenants->contains(fn (TenantData $t) => $t->slug === $tenantB->slug));
    }

    public function test_get_tenants_excludes_maintenance_tenants(): void
    {
        $user = User::factory()->create();
        $active = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false]);
        $maintenance = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => true]);

        $user->userApps()->create(['app_id' => $this->tenantApp->id, 'role' => Role::User]);
        $user->userAppTenants()->create(['app_id' => $this->tenantApp->id, 'tenant_id' => $active->id, 'role' => Role::User, 'is_default' => true]);
        $user->userAppTenants()->create(['app_id' => $this->tenantApp->id, 'tenant_id' => $maintenance->id, 'role' => Role::User, 'is_default' => false]);

        $tenants = $this->service->getTenantsForUser($user->id, 'tenant');

        $this->assertCount(2, $tenants);
        $this->assertTrue($tenants->contains(fn (TenantData $t) => $t->slug === $active->slug));
        $this->assertTrue($tenants->contains(fn (TenantData $t) => $t->slug === $maintenance->slug && $t->isMaintenance));
    }

    public function test_initialize_for_user_sets_session_with_default_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false]);

        $user->userApps()->create(['app_id' => $this->tenantApp->id, 'role' => Role::User]);
        $user->userAppTenants()->create(['app_id' => $this->tenantApp->id, 'tenant_id' => $tenant->id, 'role' => Role::User, 'is_default' => true]);

        $this->service->initializeForUser($user->id, 'tenant');

        $this->assertSame($tenant->slug, Session::get(TenantSwitcherService::SESSION_KEY));
    }

    public function test_initialize_does_not_overwrite_existing_session(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false]);

        $user->userApps()->create(['app_id' => $this->tenantApp->id, 'role' => Role::User]);
        $user->userAppTenants()->create(['app_id' => $this->tenantApp->id, 'tenant_id' => $tenant->id, 'role' => Role::User, 'is_default' => true]);

        Session::put(TenantSwitcherService::SESSION_KEY, 'already-set');

        $this->service->initializeForUser($user->id, 'tenant');

        $this->assertSame('already-set', Session::get(TenantSwitcherService::SESSION_KEY));
    }

    public function test_switch_tenant_updates_session_and_returns_true(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false]);
        $tenantB = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false]);

        $user->userApps()->create(['app_id' => $this->tenantApp->id, 'role' => Role::User]);
        $user->userAppTenants()->create(['app_id' => $this->tenantApp->id, 'tenant_id' => $tenantA->id, 'role' => Role::User, 'is_default' => true]);
        $user->userAppTenants()->create(['app_id' => $this->tenantApp->id, 'tenant_id' => $tenantB->id, 'role' => Role::User, 'is_default' => false]);

        $result = $this->service->switchTenant($user->id, 'tenant', $tenantB->slug);

        $this->assertTrue($result);
        $this->assertSame($tenantB->slug, Session::get(TenantSwitcherService::SESSION_KEY));
    }

    public function test_switch_tenant_returns_false_for_unauthorized_tenant(): void
    {
        $user = User::factory()->create();
        $assignedTenant = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false]);
        $otherTenant = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false]);

        $user->userApps()->create(['app_id' => $this->tenantApp->id, 'role' => Role::User]);
        $user->userAppTenants()->create(['app_id' => $this->tenantApp->id, 'tenant_id' => $assignedTenant->id, 'role' => Role::User, 'is_default' => true]);

        $result = $this->service->switchTenant($user->id, 'tenant', $otherTenant->slug);

        $this->assertFalse($result);
    }

    public function test_get_tenants_marks_current_tenant(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false]);
        $tenantB = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false]);

        $user->userApps()->create(['app_id' => $this->tenantApp->id, 'role' => Role::User]);
        $user->userAppTenants()->create(['app_id' => $this->tenantApp->id, 'tenant_id' => $tenantA->id, 'role' => Role::User, 'is_default' => true]);
        $user->userAppTenants()->create(['app_id' => $this->tenantApp->id, 'tenant_id' => $tenantB->id, 'role' => Role::User, 'is_default' => false]);

        Session::put(TenantSwitcherService::SESSION_KEY, $tenantB->slug);

        $tenants = $this->service->getTenantsForUser($user->id, 'tenant');

        $current = $tenants->first(fn (TenantData $t) => $t->isCurrent);
        $this->assertNotNull($current);
        $this->assertSame($tenantB->slug, $current->slug);
    }
}
