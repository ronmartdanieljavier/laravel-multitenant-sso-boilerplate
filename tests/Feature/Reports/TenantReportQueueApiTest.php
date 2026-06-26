<?php

namespace Tests\Feature\Reports;

use App\Http\Middleware\ResolveTenantDatabase;
use App\Models\Central\Report;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportStatus;
use App\Reports\Http\Controllers\TenantReportQueueApiController;
use App\Reports\Http\Requests\QuickReportRequest;
use App\Reports\Jobs\GenerateReportJob;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class TenantReportQueueApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeReport(Tenant $tenant, User $user, array $overrides = []): Report
    {
        return Report::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'type' => 'user_activity',
            'format' => ReportFormat::Screen,
            'delivery' => ReportDelivery::None,
            'status' => ReportStatus::Pending,
        ], $overrides));
    }

    private function controller(): TenantReportQueueApiController
    {
        return app(TenantReportQueueApiController::class);
    }

    private function makeRequest(Tenant $tenant, User $user): Request
    {
        $request = Request::create('/api/v1/tenant/reports');
        $request->attributes->set('current_tenant', $tenant);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/tenant/reports')->assertUnauthorized();
    }

    public function test_returns_paginated_reports_for_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $this->makeReport($tenant, $user);
        $this->makeReport($tenant, $user);

        $response = $this->controller()->index($this->makeRequest($tenant, $user));
        $data = $response->getData(true);

        $this->assertArrayHasKey('data', $data);
        $this->assertCount(2, $data['data']['data']);
    }

    public function test_returns_404_without_tenant_context(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $request = Request::create('/api/v1/tenant/reports');
        $request->setUserResolver(fn () => User::factory()->create());

        $this->controller()->index($request);
    }

    public function test_reports_are_scoped_to_tenant(): void
    {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        $this->makeReport($tenant1, $user);
        $this->makeReport($tenant2, $user);

        $data = $this->controller()->index($this->makeRequest($tenant1, $user))->getData(true);

        $this->assertCount(1, $data['data']['data']);
    }

    public function test_response_includes_status_field(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $this->makeReport($tenant, $user, ['status' => ReportStatus::Processing]);

        $data = $this->controller()->index($this->makeRequest($tenant, $user))->getData(true);

        $this->assertSame('processing', $data['data']['data'][0]['status']);
    }

    // ── Quick dispatch API ────────────────────────────────────────────────────

    public function test_quick_dispatch_unauthenticated_is_rejected(): void
    {
        $this->postJson('/api/v1/tenant/reports/quick', [
            'type' => 'documents_summary',
            'format' => 'screen',
        ])->assertUnauthorized();
    }

    public function test_quick_dispatch_creates_report_and_queues_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->withoutMiddleware(ResolveTenantDatabase::class)
            ->withServerVariables([])
            ->call('POST', '/api/v1/tenant/reports/quick', [
                'type' => 'documents_summary',
                'format' => 'screen',
            ], [], [], ['HTTP_ACCEPT' => 'application/json'], json_encode([
                'type' => 'documents_summary',
                'format' => 'screen',
            ]));

        // Test via controller directly for tenant context
        Queue::fake();

        $request = QuickReportRequest::create(
            '/api/v1/tenant/reports/quick',
            'POST',
            ['type' => 'documents_summary', 'format' => 'screen']
        );
        $request->attributes->set('current_tenant', $tenant);
        $request->setUserResolver(fn () => $user);

        $response = $this->controller()->quickDispatch($request);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertDatabaseHas('reports', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'type' => 'documents_summary',
        ]);
        Queue::assertPushed(GenerateReportJob::class);
    }

    public function test_quick_dispatch_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->withoutMiddleware(ResolveTenantDatabase::class)
            ->postJson('/api/v1/tenant/reports/quick', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'format']);
    }

    public function test_quick_dispatch_validates_format_enum(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->withoutMiddleware(ResolveTenantDatabase::class)
            ->postJson('/api/v1/tenant/reports/quick', [
                'type' => 'documents_summary',
                'format' => 'fax',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['format']);
    }

    // ── Retry API ─────────────────────────────────────────────────────────────

    public function test_retry_resets_failed_report_and_queues_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $report = $this->makeReport($tenant, $user, [
            'status' => ReportStatus::Failed,
            'error_message' => 'Something went wrong',
        ]);

        $request = Request::create("/api/v1/tenant/reports/{$report->id}/retry", 'POST');
        $request->attributes->set('current_tenant', $tenant);
        $request->setUserResolver(fn () => $user);

        $response = $this->controller()->retry($request, $report->id);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'pending',
            'error_message' => null,
        ]);
        Queue::assertPushed(GenerateReportJob::class);
    }

    public function test_retry_is_rejected_for_reports_belonging_to_another_tenant(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        $report = $this->makeReport($tenant2, $user, ['status' => ReportStatus::Failed]);

        $request = Request::create("/api/v1/tenant/reports/{$report->id}/retry", 'POST');
        $request->attributes->set('current_tenant', $tenant1);
        $request->setUserResolver(fn () => $user);

        $this->expectException(HttpException::class);
        $this->controller()->retry($request, $report->id);
    }

    public function test_quick_dispatch_returns_201_with_report_data(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $request = QuickReportRequest::create(
            '/api/v1/tenant/reports/quick',
            'POST',
            ['type' => 'documents_summary', 'format' => 'pdf']
        );
        $request->attributes->set('current_tenant', $tenant);
        $request->setUserResolver(fn () => $user);

        $response = $this->controller()->quickDispatch($request);
        $data = $response->getData(true);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);
        $this->assertSame('documents_summary', $data['data']['type']);
        $this->assertSame('pdf', $data['data']['format']);
    }
}
