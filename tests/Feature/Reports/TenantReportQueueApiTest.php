<?php

namespace Tests\Feature\Reports;

use App\Models\Central\Report;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportStatus;
use App\Reports\Http\Controllers\TenantReportQueueApiController;
use App\Repositories\Central\ReportRepository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
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
        return new TenantReportQueueApiController(
            app(ReportRepository::class)
        );
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
}
