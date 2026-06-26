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

class TenantSwitcherWebTest extends TestCase
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

    public function test_unauthenticated_user_cannot_switch_tenant(): void
    {
        $this->post('/tenant/switch', ['tenant_slug' => 'some-tenant'])
            ->assertRedirect('/login');
    }

    public function test_switch_to_assigned_tenant_redirects_to_dashboard(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false, 'db_host' => '127.0.0.1']);
        $tenantB = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false, 'db_host' => '127.0.0.1']);

        $this->assignUserToTenant($user, $tenantA, true);
        $this->assignUserToTenant($user, $tenantB, false);

        Session::put(TenantSwitcherService::SESSION_KEY, $tenantA->slug);

        $this->actingAs($user)
            ->post('/tenant/switch', ['tenant_slug' => $tenantB->slug])
            ->assertRedirect('/tenant');

        $this->assertSame($tenantB->slug, Session::get(TenantSwitcherService::SESSION_KEY));
    }

    public function test_switch_to_unassigned_tenant_returns_error(): void
    {
        $user = User::factory()->create();
        $assignedTenant = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false, 'db_host' => '127.0.0.1']);
        $otherTenant = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false, 'db_host' => '127.0.0.1']);

        $this->assignUserToTenant($user, $assignedTenant);
        Session::put(TenantSwitcherService::SESSION_KEY, $assignedTenant->slug);

        $this->actingAs($user)
            ->post('/tenant/switch', ['tenant_slug' => $otherTenant->slug])
            ->assertSessionHasErrors('tenant_slug');
    }

    public function test_switch_requires_tenant_slug(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false, 'db_host' => '127.0.0.1']);

        $this->assignUserToTenant($user, $tenant);
        Session::put(TenantSwitcherService::SESSION_KEY, $tenant->slug);

        $this->actingAs($user)
            ->post('/tenant/switch', [])
            ->assertSessionHasErrors('tenant_slug');
    }
}
