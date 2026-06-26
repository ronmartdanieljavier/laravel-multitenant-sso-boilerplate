<?php

namespace App\Tenant\Tests;

use App\Models\Central\Report;
use App\Models\Central\Tenant;
use App\Models\Central\TenantErrorLog;
use App\Models\Central\User;
use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportStatus;
use App\Tenant\Data\TenantDashboardData;
use App\Tenant\Data\TenantDashboardStatsData;
use App\Tenant\Services\TenantDashboardService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TenantDashboardServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private TenantDashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
        ]);

        $this->service = app(TenantDashboardService::class);
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

    private function makeErrorLog(Tenant $tenant, string $severity = 'error', ?string $resolvedAt = null): TenantErrorLog
    {
        return TenantErrorLog::create([
            'tenant_id' => $tenant->id,
            'error_code' => 'E-SVC-'.fake()->unique()->regexify('[A-Z0-9]{8}'),
            'exception_class' => 'RuntimeException',
            'message' => 'Test error',
            'severity' => $severity,
            'resolved_at' => $resolvedAt,
        ]);
    }

    public function test_get_dashboard_returns_dashboard_data(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'svc-basic']);
        $user = User::factory()->create();
        $this->makeReport($tenant, $user);

        $result = $this->service->getDashboard($tenant->id);

        $this->assertInstanceOf(TenantDashboardData::class, $result);
        $this->assertInstanceOf(TenantDashboardStatsData::class, $result->stats);
    }

    public function test_stats_reflect_pending_and_failed_report_counts(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'svc-counts']);
        $user = User::factory()->create();
        $this->makeReport($tenant, $user, ReportStatus::Pending);
        $this->makeReport($tenant, $user, ReportStatus::Pending);
        $this->makeReport($tenant, $user, ReportStatus::Failed);

        $result = $this->service->getDashboard($tenant->id);

        $this->assertSame(2, $result->stats->pendingReports);
        $this->assertSame(1, $result->stats->failedReports);
    }

    public function test_stats_reflect_unresolved_and_critical_error_counts(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'svc-errors']);
        $this->makeErrorLog($tenant, 'error');
        $this->makeErrorLog($tenant, 'critical');
        $this->makeErrorLog($tenant, 'error', now()->toDateTimeString());

        $result = $this->service->getDashboard($tenant->id);

        $this->assertSame(2, $result->stats->unresolvedErrors);
        $this->assertSame(1, $result->stats->criticalErrors);
    }

    public function test_recent_errors_only_includes_unresolved(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'svc-unres']);
        $this->makeErrorLog($tenant, 'error');
        $this->makeErrorLog($tenant, 'error', now()->toDateTimeString());

        $result = $this->service->getDashboard($tenant->id);

        $this->assertCount(1, $result->recentErrors);
    }

    public function test_recent_errors_capped_at_five(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'svc-cap']);
        for ($i = 0; $i < 8; $i++) {
            $this->makeErrorLog($tenant);
        }

        $result = $this->service->getDashboard($tenant->id);

        $this->assertLessThanOrEqual(5, $result->recentErrors->count());
    }

    public function test_recent_reports_capped_at_five(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'svc-rpt-cap']);
        $user = User::factory()->create();
        for ($i = 0; $i < 8; $i++) {
            $this->makeReport($tenant, $user);
        }

        $result = $this->service->getDashboard($tenant->id);

        $this->assertLessThanOrEqual(5, $result->recentReports->count());
    }

    public function test_stats_scoped_to_tenant(): void
    {
        $tenant1 = Tenant::factory()->create(['slug' => 'svc-t1']);
        $tenant2 = Tenant::factory()->create(['slug' => 'svc-t2']);
        $user = User::factory()->create();
        $this->makeReport($tenant1, $user, ReportStatus::Failed);
        $this->makeReport($tenant2, $user, ReportStatus::Failed);
        $this->makeReport($tenant2, $user, ReportStatus::Failed);

        $result = $this->service->getDashboard($tenant1->id);

        $this->assertSame(1, $result->stats->failedReports);
    }

    public function test_stats_reflect_processing_report_count(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'svc-proc']);
        $user = User::factory()->create();
        $this->makeReport($tenant, $user, ReportStatus::Processing);
        $this->makeReport($tenant, $user, ReportStatus::Processing);

        $result = $this->service->getDashboard($tenant->id);

        $this->assertSame(2, $result->stats->processingReports);
    }

    public function test_success_reports_this_month_excludes_other_months(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'svc-month']);
        $user = User::factory()->create();

        Report::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'type' => 'user_activity',
            'format' => ReportFormat::Screen,
            'delivery' => ReportDelivery::None,
            'status' => ReportStatus::Success,
            'completed_at' => now(),
        ]);

        Report::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'type' => 'user_activity',
            'format' => ReportFormat::Screen,
            'delivery' => ReportDelivery::None,
            'status' => ReportStatus::Success,
            'completed_at' => now()->subMonths(2),
        ]);

        $result = $this->service->getDashboard($tenant->id);

        $this->assertSame(1, $result->stats->successReportsThisMonth);
    }

    public function test_all_stats_zero_for_tenant_with_no_activity(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'svc-zero']);

        $result = $this->service->getDashboard($tenant->id);

        $this->assertSame(0, $result->stats->pendingReports);
        $this->assertSame(0, $result->stats->processingReports);
        $this->assertSame(0, $result->stats->failedReports);
        $this->assertSame(0, $result->stats->successReportsThisMonth);
        $this->assertSame(0, $result->stats->unresolvedErrors);
        $this->assertSame(0, $result->stats->criticalErrors);
        $this->assertSame(0, $result->stats->totalDocuments);
        $this->assertCount(0, $result->recentReports);
        $this->assertCount(0, $result->recentErrors);
        $this->assertCount(0, $result->recentDocuments);
    }
}
