<?php

namespace App\Admin\Tests;

use App\Models\Central\Report;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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

    public function test_unauthenticated_request_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();

        $this->getJson(route('admin.api.tenants.reports', $tenant))->assertUnauthorized();
    }

    public function test_returns_paginated_reports_for_tenant(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        $tenant = Tenant::factory()->create();
        $this->makeReport($tenant, $user);
        $this->makeReport($tenant, $user);

        $this->actingAs($user, 'sanctum')
            ->getJson(route('admin.api.tenants.reports', $tenant))
            ->assertOk()
            ->assertJsonPath('data.total', 2);
    }

    public function test_reports_are_scoped_to_tenant(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        $this->makeReport($tenant1, $user);
        $this->makeReport($tenant2, $user);

        $this->actingAs($user, 'sanctum')
            ->getJson(route('admin.api.tenants.reports', $tenant1))
            ->assertJsonPath('data.total', 1);
    }

    public function test_response_includes_status_field(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        $tenant = Tenant::factory()->create();
        $this->makeReport($tenant, $user, ['status' => ReportStatus::Failed]);

        $this->actingAs($user, 'sanctum')
            ->getJson(route('admin.api.tenants.reports', $tenant))
            ->assertJsonPath('data.data.0.status', 'failed');
    }
}
