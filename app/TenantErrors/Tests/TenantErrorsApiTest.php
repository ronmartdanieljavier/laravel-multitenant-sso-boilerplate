<?php

namespace App\TenantErrors\Tests;

use App\Models\Central\Tenant;
use App\Models\Central\TenantErrorLog;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\WithFakeTenantContext;
use Tests\TestCase;

class TenantErrorsApiTest extends TestCase
{
    use LazilyRefreshDatabase, WithFakeTenantContext;

    private function errorLog(Tenant $tenant, array $overrides = []): TenantErrorLog
    {
        return TenantErrorLog::create(array_merge([
            'tenant_id' => $tenant->id,
            'error_code' => 'E-API-AAAABBBB',
            'exception_class' => 'RuntimeException',
            'message' => 'Test error',
            'severity' => 'error',
        ], $overrides));
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'apiauth']);
        $this->setFakeTenant($tenant);

        $this->getJson(route('tenant.api.errors.index'))->assertUnauthorized();
    }

    public function test_returns_error_list_for_current_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'api']);
        $this->errorLog($tenant, ['error_code' => 'E-API-00000001']);
        $this->setFakeTenant($tenant);

        $this->actingAs($user, 'sanctum')
            ->getJson(route('tenant.api.errors.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.error_code', 'E-API-00000001');
    }

    public function test_error_list_scoped_to_current_tenant(): void
    {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create(['slug' => 'apiscope1']);
        $tenant2 = Tenant::factory()->create(['slug' => 'apiscope2']);
        $this->errorLog($tenant1, ['error_code' => 'E-A1-00000001']);
        $this->errorLog($tenant2, ['error_code' => 'E-A2-00000001']);
        $this->setFakeTenant($tenant1);

        $this->actingAs($user, 'sanctum')
            ->getJson(route('tenant.api.errors.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_returns_single_error_detail(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'apishow']);
        $log = $this->errorLog($tenant, ['error_code' => 'E-SHW-00000001']);
        $this->setFakeTenant($tenant);

        $this->actingAs($user, 'sanctum')
            ->getJson(route('tenant.api.errors.show', $log->id))
            ->assertOk()
            ->assertJsonPath('data.error_code', 'E-SHW-00000001');
    }

    public function test_show_returns_404_for_another_tenants_log(): void
    {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create(['slug' => 'apiown']);
        $tenant2 = Tenant::factory()->create(['slug' => 'apiother']);
        $log = $this->errorLog($tenant2, ['error_code' => 'E-AOT-00000001']);
        $this->setFakeTenant($tenant1);

        $this->actingAs($user, 'sanctum')
            ->getJson(route('tenant.api.errors.show', $log->id))
            ->assertNotFound();
    }

    public function test_filters_by_severity(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'apisev']);
        $this->errorLog($tenant, ['error_code' => 'E-SV-00000001', 'severity' => 'error']);
        $this->errorLog($tenant, ['error_code' => 'E-SV-00000002', 'severity' => 'warning']);
        $this->setFakeTenant($tenant);

        $this->actingAs($user, 'sanctum')
            ->getJson(route('tenant.api.errors.index', ['severity' => 'warning']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.severity', 'warning');
    }

    public function test_response_does_not_expose_trace_or_request_headers(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'apimask']);
        $log = $this->errorLog($tenant, [
            'error_code' => 'E-MSK-00000001',
            'trace' => [['file' => '/app/foo.php', 'line' => 1, 'function' => 'bar']],
            'request_headers' => ['authorization' => 'Bearer secret'],
        ]);
        $this->setFakeTenant($tenant);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson(route('tenant.api.errors.show', $log->id))
            ->assertOk()
            ->json('data');

        $this->assertArrayNotHasKey('trace', $response);
        $this->assertArrayNotHasKey('request_headers', $response);
    }
}
