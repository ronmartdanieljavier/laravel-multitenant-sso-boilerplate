<?php

namespace App\Tenant\Tests;

use App\Models\Central\Report;
use App\Models\Central\Tenant;
use App\Models\Central\TenantErrorLog;
use App\Models\Central\User;
use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportStatus;
use App\Tenant\Http\Controllers\TenantDashboardApiController;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class TenantDashboardApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
        ]);
    }

    private function controller(): TenantDashboardApiController
    {
        return app(TenantDashboardApiController::class);
    }

    private function makeRequest(Tenant $tenant, User $user): Request
    {
        $request = Request::create('/api/v1/tenant/dashboard');
        $request->attributes->set('current_tenant', $tenant);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function makeReport(Tenant $tenant, User $user, ReportStatus $status = ReportStatus::Pending): Report
    {
        return Report::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'type' => 'user_activity',
            'format' => ReportFormat::Screen,
            'delivery' => ReportDelivery::None,
            'status' => $status,
        ]);
    }

    private function makeErrorLog(Tenant $tenant, string $severity = 'error'): TenantErrorLog
    {
        return TenantErrorLog::create([
            'tenant_id' => $tenant->id,
            'error_code' => 'E-API-'.fake()->unique()->regexify('[A-Z0-9]{8}'),
            'exception_class' => 'RuntimeException',
            'message' => 'Test error',
            'severity' => $severity,
        ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/tenant/dashboard')->assertUnauthorized();
    }

    public function test_returns_dashboard_data_shape(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'api-shape']);

        $response = $this->controller()->index($this->makeRequest($tenant, $user));
        $data = $response->getData(true)['data'];

        $this->assertArrayHasKey('stats', $data);
        $this->assertArrayHasKey('recentReports', $data);
        $this->assertArrayHasKey('recentErrors', $data);
        $this->assertArrayHasKey('recentDocuments', $data);
    }

    public function test_stats_contain_expected_keys(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'api-keys']);

        $response = $this->controller()->index($this->makeRequest($tenant, $user));
        $stats = $response->getData(true)['data']['stats'];

        $this->assertArrayHasKey('pending_reports', $stats);
        $this->assertArrayHasKey('failed_reports', $stats);
        $this->assertArrayHasKey('unresolved_errors', $stats);
        $this->assertArrayHasKey('total_documents', $stats);
    }

    public function test_stats_reflect_pending_reports(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'api-pending']);
        $this->makeReport($tenant, $user, ReportStatus::Pending);
        $this->makeReport($tenant, $user, ReportStatus::Pending);

        $response = $this->controller()->index($this->makeRequest($tenant, $user));
        $stats = $response->getData(true)['data']['stats'];

        $this->assertSame(2, $stats['pending_reports']);
    }

    public function test_stats_reflect_unresolved_errors(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'api-errs']);
        $this->makeErrorLog($tenant, 'critical');
        $this->makeErrorLog($tenant, 'error');

        $response = $this->controller()->index($this->makeRequest($tenant, $user));
        $stats = $response->getData(true)['data']['stats'];

        $this->assertSame(2, $stats['unresolved_errors']);
        $this->assertSame(1, $stats['critical_errors']);
    }

    public function test_returns_404_without_tenant_context(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $request = Request::create('/api/v1/tenant/dashboard');
        $request->setUserResolver(fn () => User::factory()->create());

        $this->controller()->index($request);
    }

    public function test_recent_reports_scoped_to_tenant(): void
    {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create(['slug' => 'api-t1']);
        $tenant2 = Tenant::factory()->create(['slug' => 'api-t2']);
        $this->makeReport($tenant1, $user);
        $this->makeReport($tenant2, $user);
        $this->makeReport($tenant2, $user);

        $response = $this->controller()->index($this->makeRequest($tenant1, $user));
        $reports = $response->getData(true)['data']['recentReports'];

        $this->assertCount(1, $reports);
    }

    public function test_stats_reflect_failed_reports(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'api-failed']);
        $this->makeReport($tenant, $user, ReportStatus::Failed);
        $this->makeReport($tenant, $user, ReportStatus::Failed);

        $response = $this->controller()->index($this->makeRequest($tenant, $user));
        $stats = $response->getData(true)['data']['stats'];

        $this->assertSame(2, $stats['failed_reports']);
    }

    public function test_stats_reflect_critical_errors(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'api-crit']);
        $this->makeErrorLog($tenant, 'critical');
        $this->makeErrorLog($tenant, 'error');

        $response = $this->controller()->index($this->makeRequest($tenant, $user));
        $stats = $response->getData(true)['data']['stats'];

        $this->assertSame(1, $stats['critical_errors']);
        $this->assertSame(2, $stats['unresolved_errors']);
    }

    public function test_recent_errors_only_include_unresolved(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'api-unres']);
        $this->makeErrorLog($tenant);
        TenantErrorLog::create([
            'tenant_id' => $tenant->id,
            'error_code' => 'E-API-RESOLVED1',
            'exception_class' => 'RuntimeException',
            'message' => 'Resolved',
            'severity' => 'error',
            'resolved_at' => now(),
        ]);

        $response = $this->controller()->index($this->makeRequest($tenant, $user));
        $errors = $response->getData(true)['data']['recentErrors'];

        $this->assertCount(1, $errors);
    }

    public function test_returns_200_status(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'api-status']);

        $response = $this->controller()->index($this->makeRequest($tenant, $user));

        $this->assertSame(200, $response->getStatusCode());
    }
}
