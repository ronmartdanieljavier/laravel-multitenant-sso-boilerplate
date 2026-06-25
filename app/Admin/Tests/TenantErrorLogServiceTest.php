<?php

namespace App\Admin\Tests;

use App\Admin\Data\TenantErrorLogData;
use App\Admin\Services\TenantErrorLogService;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use RuntimeException;
use Tests\TestCase;

class TenantErrorLogServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private TenantErrorLogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TenantErrorLogService::class);
    }

    private function tenant(): Tenant
    {
        return Tenant::factory()->create(['slug' => 'acme']);
    }

    private function makeRequest(array $params = [], ?User $user = null): Request
    {
        $request = Request::create('/api/v1/tenant/orders', 'POST', $params);
        $request->headers->set('X-App', 'myapp');
        $request->headers->set('X-Tenant', 'acme');

        if ($user) {
            $request->setUserResolver(fn () => $user);
        }

        return $request;
    }

    public function test_record_creates_a_dto_with_an_error_code(): void
    {
        $tenant = $this->tenant();
        $exception = new RuntimeException('Something broke');

        $result = $this->service->record($exception, $tenant, $this->makeRequest());

        $this->assertInstanceOf(TenantErrorLogData::class, $result);
        $this->assertStringStartsWith('E-ACME-', $result->errorCode);
        $this->assertSame('RuntimeException', $result->exceptionClass);
        $this->assertSame('Something broke', $result->message);
        $this->assertSame('error', $result->severity);
    }

    public function test_record_stores_request_url_method_and_params(): void
    {
        $tenant = $this->tenant();
        $exception = new RuntimeException('Test');
        $request = $this->makeRequest(['order_id' => 123, 'password' => 'secret']);

        $result = $this->service->record($exception, $tenant, $request);

        $this->assertStringContainsString('/api/v1/tenant/orders', $result->requestUrl);
        $this->assertSame('POST', $result->requestMethod);
        $this->assertSame(123, $result->requestParams['order_id']);
        $this->assertSame('[REDACTED]', $result->requestParams['password']);
    }

    public function test_record_stores_user_id_when_authenticated(): void
    {
        $tenant = $this->tenant();
        $user = User::factory()->create();
        $exception = new RuntimeException('Test');

        $result = $this->service->record($exception, $tenant, $this->makeRequest([], $user));

        $this->assertSame($user->id, $result->userId);
    }

    public function test_record_redacts_authorization_header(): void
    {
        $tenant = $this->tenant();
        $request = $this->makeRequest();
        $request->headers->set('Authorization', 'Bearer super-secret-token');

        $result = $this->service->record(new RuntimeException('Test'), $tenant, $request);

        $this->assertSame('[REDACTED]', $result->requestHeaders['authorization']);
    }

    public function test_record_with_custom_severity(): void
    {
        $tenant = $this->tenant();

        $result = $this->service->record(
            new RuntimeException('Critical!'),
            $tenant,
            $this->makeRequest(),
            'critical'
        );

        $this->assertSame('critical', $result->severity);
    }

    public function test_error_codes_are_unique_across_records(): void
    {
        $tenant = $this->tenant();

        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $result = $this->service->record(new RuntimeException("Error {$i}"), $tenant, $this->makeRequest());
            $codes[] = $result->errorCode;
        }

        $this->assertCount(10, array_unique($codes), 'Error codes must be unique');
    }

    public function test_list_returns_logs_for_tenant(): void
    {
        $tenant = $this->tenant();
        $this->service->record(new RuntimeException('First'), $tenant, $this->makeRequest());
        $this->service->record(new RuntimeException('Second'), $tenant, $this->makeRequest());

        $logs = $this->service->listForTenant($tenant->id);

        $this->assertCount(2, $logs);
    }

    public function test_list_filters_by_severity(): void
    {
        $tenant = $this->tenant();
        $this->service->record(new RuntimeException('error-level'), $tenant, $this->makeRequest(), 'error');
        $this->service->record(new RuntimeException('critical-level'), $tenant, $this->makeRequest(), 'critical');

        $logs = $this->service->listForTenant($tenant->id, 'critical');

        $this->assertCount(1, $logs);
        $this->assertSame('critical', $logs->first()->severity);
    }

    public function test_list_filters_unresolved_only(): void
    {
        $tenant = $this->tenant();
        $log = $this->service->record(new RuntimeException('resolved'), $tenant, $this->makeRequest());
        $this->service->resolve($log->id);
        $this->service->record(new RuntimeException('open'), $tenant, $this->makeRequest());

        $logs = $this->service->listForTenant($tenant->id, unresolvedOnly: true);

        $this->assertCount(1, $logs);
        $this->assertFalse($logs->first()->resolved);
    }

    public function test_resolve_marks_log_as_resolved(): void
    {
        $tenant = $this->tenant();
        $log = $this->service->record(new RuntimeException('Test'), $tenant, $this->makeRequest());

        $this->assertFalse($log->resolved);

        $this->service->resolve($log->id);
        $updated = $this->service->getById($log->id);

        $this->assertTrue($updated->resolved);
        $this->assertNotNull($updated->resolvedAt);
    }

    public function test_unresolve_reopens_a_resolved_log(): void
    {
        $tenant = $this->tenant();
        $log = $this->service->record(new RuntimeException('Test'), $tenant, $this->makeRequest());
        $this->service->resolve($log->id);

        $this->service->unresolve($log->id);
        $updated = $this->service->getById($log->id);

        $this->assertFalse($updated->resolved);
        $this->assertNull($updated->resolvedAt);
    }

    public function test_get_by_code_returns_matching_log(): void
    {
        $tenant = $this->tenant();
        $log = $this->service->record(new RuntimeException('Test'), $tenant, $this->makeRequest());

        $found = $this->service->getByCode($log->errorCode);

        $this->assertNotNull($found);
        $this->assertSame($log->id, $found->id);
    }

    public function test_get_by_code_returns_null_for_unknown_code(): void
    {
        $this->assertNull($this->service->getByCode('E-ACME-NOTFOUND'));
    }

    public function test_logs_are_isolated_between_tenants(): void
    {
        $t1 = $this->tenant();
        $t2 = Tenant::factory()->create(['slug' => 'other']);

        $this->service->record(new RuntimeException('T1 error'), $t1, $this->makeRequest());

        $this->assertCount(0, $this->service->listForTenant($t2->id));
    }

    public function test_delete_removes_log(): void
    {
        $tenant = $this->tenant();
        $log = $this->service->record(new RuntimeException('Test'), $tenant, $this->makeRequest());

        $this->service->delete($log->id);

        $this->assertNull($this->service->getByCode($log->errorCode));
        $this->assertDatabaseMissing('tenant_error_logs', ['id' => $log->id]);
    }
}
