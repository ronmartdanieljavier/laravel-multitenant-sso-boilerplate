<?php

namespace App\TenantErrors\Tests;

use App\Models\Central\Tenant;
use App\Models\Central\TenantErrorLog;
use App\TenantErrors\Data\TenantErrorData;
use App\TenantErrors\Services\TenantErrorsPortalService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class TenantErrorsServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private TenantErrorsPortalService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TenantErrorsPortalService::class);
    }

    private function errorLog(Tenant $tenant, array $overrides = []): TenantErrorLog
    {
        return TenantErrorLog::create(array_merge([
            'tenant_id' => $tenant->id,
            'error_code' => 'E-TEST-AAAABBBB',
            'exception_class' => 'RuntimeException',
            'message' => 'Test error',
            'severity' => 'error',
        ], $overrides));
    }

    public function test_list_for_tenant_returns_tenant_error_data_collection(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'test']);
        $this->errorLog($tenant);

        $result = $this->service->listForTenant($tenant->id);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(TenantErrorData::class, $result->first());
    }

    public function test_list_for_tenant_is_scoped_to_tenant(): void
    {
        $tenant1 = Tenant::factory()->create(['slug' => 'tenant1']);
        $tenant2 = Tenant::factory()->create(['slug' => 'tenant2']);
        $this->errorLog($tenant1, ['error_code' => 'E-T1-00000001']);
        $this->errorLog($tenant2, ['error_code' => 'E-T2-00000001']);

        $result = $this->service->listForTenant($tenant1->id);

        $this->assertCount(1, $result);
        $this->assertSame('E-T1-00000001', $result->first()->errorCode);
    }

    public function test_list_filters_by_severity(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'sev']);
        $this->errorLog($tenant, ['error_code' => 'E-SEV-00000001', 'severity' => 'error']);
        $this->errorLog($tenant, ['error_code' => 'E-SEV-00000002', 'severity' => 'warning']);

        $result = $this->service->listForTenant($tenant->id, 'error');

        $this->assertCount(1, $result);
        $this->assertSame('error', $result->first()->severity);
    }

    public function test_list_filters_unresolved_only(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'unres']);
        $this->errorLog($tenant, ['error_code' => 'E-UNR-00000001']);
        $this->errorLog($tenant, ['error_code' => 'E-UNR-00000002', 'resolved_at' => now()]);

        $result = $this->service->listForTenant($tenant->id, null, true);

        $this->assertCount(1, $result);
        $this->assertFalse($result->first()->resolved);
    }

    public function test_get_for_tenant_returns_tenant_error_data(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'get']);
        $log = $this->errorLog($tenant);

        $result = $this->service->getForTenant($tenant->id, $log->id);

        $this->assertInstanceOf(TenantErrorData::class, $result);
        $this->assertSame($log->id, $result->id);
    }

    public function test_get_for_tenant_aborts_if_log_belongs_to_different_tenant(): void
    {
        $tenant1 = Tenant::factory()->create(['slug' => 'own']);
        $tenant2 = Tenant::factory()->create(['slug' => 'other']);
        $log = $this->errorLog($tenant2, ['error_code' => 'E-OTH-00000001']);

        $this->expectException(NotFoundHttpException::class);

        $this->service->getForTenant($tenant1->id, $log->id);
    }

    public function test_data_does_not_expose_trace_or_request_headers(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'mask']);
        $log = $this->errorLog($tenant, [
            'trace' => [['file' => '/app/foo.php', 'line' => 1, 'function' => 'bar']],
            'request_headers' => ['authorization' => 'Bearer secret'],
        ]);

        $result = $this->service->getForTenant($tenant->id, $log->id);

        $data = $result->toArray();
        $this->assertArrayNotHasKey('trace', $data);
        $this->assertArrayNotHasKey('requestHeaders', $data);
        $this->assertArrayNotHasKey('request_headers', $data);
    }
}
