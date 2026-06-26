<?php

namespace App\TenantErrors\Tests;

use App\Models\Central\Tenant;
use App\Models\Central\TenantErrorLog;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\WithFakeTenantContext;
use Tests\TestCase;

class TenantErrorsWebTest extends TestCase
{
    use LazilyRefreshDatabase, WithFakeTenantContext;

    private function errorLog(Tenant $tenant, array $overrides = []): TenantErrorLog
    {
        return TenantErrorLog::create(array_merge([
            'tenant_id' => $tenant->id,
            'error_code' => 'E-WEB-AAAABBBB',
            'exception_class' => 'RuntimeException',
            'message' => 'Test error',
            'severity' => 'error',
        ], $overrides));
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        $this->setFakeTenant($tenant);

        $this->get(route('tenant.errors'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_error_list(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'list']);
        $this->errorLog($tenant);
        $this->setFakeTenant($tenant);

        $this->actingAs($user)
            ->get(route('tenant.errors'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/ErrorLogs')
                ->has('logs', 1)
            );
    }

    public function test_error_list_is_scoped_to_current_tenant(): void
    {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create(['slug' => 'scope1']);
        $tenant2 = Tenant::factory()->create(['slug' => 'scope2']);
        $this->errorLog($tenant1, ['error_code' => 'E-S1-00000001']);
        $this->errorLog($tenant2, ['error_code' => 'E-S2-00000001']);
        $this->setFakeTenant($tenant1);

        $this->actingAs($user)
            ->get(route('tenant.errors'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('logs', 1)
                ->where('logs.0.error_code', 'E-S1-00000001')
            );
    }

    public function test_authenticated_user_can_view_error_detail(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'detail']);
        $log = $this->errorLog($tenant, ['error_code' => 'E-DTL-00000001']);
        $this->setFakeTenant($tenant);

        $this->actingAs($user)
            ->get(route('tenant.errors.show', $log->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/ErrorLog')
                ->where('log.error_code', 'E-DTL-00000001')
            );
    }

    public function test_error_detail_returns_404_for_another_tenants_log(): void
    {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create(['slug' => 'own']);
        $tenant2 = Tenant::factory()->create(['slug' => 'other']);
        $log = $this->errorLog($tenant2, ['error_code' => 'E-OTH-00000001']);
        $this->setFakeTenant($tenant1);

        $this->actingAs($user)
            ->get(route('tenant.errors.show', $log->id))
            ->assertNotFound();
    }

    public function test_error_list_filters_by_severity(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'fsev']);
        $this->errorLog($tenant, ['error_code' => 'E-FS-00000001', 'severity' => 'error']);
        $this->errorLog($tenant, ['error_code' => 'E-FS-00000002', 'severity' => 'warning']);
        $this->setFakeTenant($tenant);

        $this->actingAs($user)
            ->get(route('tenant.errors', ['severity' => 'error']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('logs', 1));
    }

    public function test_error_list_filters_unresolved_only(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'funr']);
        $this->errorLog($tenant, ['error_code' => 'E-UN-00000001']);
        $this->errorLog($tenant, ['error_code' => 'E-UN-00000002', 'resolved_at' => now()]);
        $this->setFakeTenant($tenant);

        $this->actingAs($user)
            ->get(route('tenant.errors', ['unresolved' => 1]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('logs', 1));
    }
}
