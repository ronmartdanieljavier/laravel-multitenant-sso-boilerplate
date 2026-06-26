<?php

namespace App\Admin\Tests;

use App\Admin\Data\DashboardStatsData;
use App\Admin\Data\MigrationComplianceSummaryData;
use App\Admin\Data\ReportQueueSummaryData;
use App\Admin\Data\TenantHealthSummaryData;
use App\Admin\Services\DashboardService;
use App\Models\Central\App;
use App\Models\Central\Report;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DashboardService::class);
    }

    public function test_get_stats_returns_dashboard_stats_data(): void
    {
        $result = $this->service->getStats();

        $this->assertInstanceOf(DashboardStatsData::class, $result);
    }

    public function test_get_health_summary_returns_tenant_health_summary_data(): void
    {
        $result = $this->service->getHealthSummary();

        $this->assertInstanceOf(TenantHealthSummaryData::class, $result);
    }

    public function test_health_summary_total_equals_sum_of_statuses(): void
    {
        $result = $this->service->getHealthSummary();

        $this->assertSame($result->total, $result->healthy + $result->warning + $result->critical);
    }

    public function test_total_users_equals_active_plus_pending(): void
    {
        User::factory()->count(3)->create(['is_active' => true]);
        for ($i = 0; $i < 2; $i++) {
            User::factory()->create(['is_active' => false, 'invitation_token' => Str::random(64)]);
        }

        $result = $this->service->getStats();

        $this->assertSame($result->activeUsers + $result->pendingInvitationUsers, $result->totalUsers);
    }

    public function test_active_users_counts_only_active_users(): void
    {
        $before = $this->service->getStats()->activeUsers;

        User::factory()->count(2)->create(['is_active' => true]);
        User::factory()->create(['is_active' => false, 'invitation_token' => Str::random(64)]);

        $result = $this->service->getStats();

        $this->assertSame($before + 2, $result->activeUsers);
    }

    public function test_pending_invitation_users_counts_only_invited(): void
    {
        $before = $this->service->getStats()->pendingInvitationUsers;

        for ($i = 0; $i < 3; $i++) {
            User::factory()->create(['is_active' => false, 'invitation_token' => Str::random(64)]);
        }
        User::factory()->create(['is_active' => true]);

        $result = $this->service->getStats();

        $this->assertSame($before + 3, $result->pendingInvitationUsers);
    }

    public function test_active_apps_counts_only_active_apps(): void
    {
        $before = $this->service->getStats()->activeApps;

        App::factory()->create(['is_active' => true]);
        App::factory()->create(['is_active' => false]);

        $result = $this->service->getStats();

        $this->assertSame($before + 1, $result->activeApps);
    }

    public function test_active_tenants_counts_only_active_tenants(): void
    {
        $before = $this->service->getStats()->activeTenants;

        Tenant::factory()->create(['is_active' => true]);
        Tenant::factory()->create(['is_active' => false]);

        $result = $this->service->getStats();

        $this->assertSame($before + 1, $result->activeTenants);
    }

    public function test_active_sso_sessions_increments_when_pat_is_created(): void
    {
        $before = $this->service->getStats()->activeSsoSessions;

        $user = User::factory()->create();
        $user->createToken('mobile-app');

        $result = $this->service->getStats();

        $this->assertSame($before + 1, $result->activeSsoSessions);
    }

    public function test_active_sso_sessions_counts_tokens_for_multiple_users(): void
    {
        $before = $this->service->getStats()->activeSsoSessions;

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userA->createToken('token-a');
        $userB->createToken('token-b1');
        $userB->createToken('token-b2');

        $result = $this->service->getStats();

        $this->assertSame($before + 3, $result->activeSsoSessions);
    }

    public function test_pending_invitation_users_excludes_inactive_users_without_token(): void
    {
        $before = $this->service->getStats()->pendingInvitationUsers;

        User::factory()->create(['is_active' => false, 'invitation_token' => null]);

        $result = $this->service->getStats();

        $this->assertSame($before, $result->pendingInvitationUsers);
    }

    public function test_total_users_excludes_inactive_users_without_token(): void
    {
        $before = $this->service->getStats()->totalUsers;

        User::factory()->create(['is_active' => false, 'invitation_token' => null]);

        $result = $this->service->getStats();

        $this->assertSame($before, $result->totalUsers);
    }

    private function report(Tenant $tenant, User $user, ReportStatus $status): void
    {
        Report::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'type' => 'orders',
            'format' => ReportFormat::Screen->value,
            'delivery' => ReportDelivery::Download->value,
            'status' => $status->value,
        ]);
    }

    public function test_get_report_queue_summary_returns_correct_type(): void
    {
        $result = $this->service->getReportQueueSummary();

        $this->assertInstanceOf(ReportQueueSummaryData::class, $result);
    }

    public function test_get_report_queue_summary_counts_pending(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $before = $this->service->getReportQueueSummary()->pending;

        $this->report($tenant, $user, ReportStatus::Pending);
        $this->report($tenant, $user, ReportStatus::Success);

        $result = $this->service->getReportQueueSummary();

        $this->assertSame($before + 1, $result->pending);
    }

    public function test_get_report_queue_summary_counts_processing(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $before = $this->service->getReportQueueSummary()->processing;

        $this->report($tenant, $user, ReportStatus::Processing);

        $result = $this->service->getReportQueueSummary();

        $this->assertSame($before + 1, $result->processing);
    }

    public function test_get_report_queue_summary_counts_failed(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $before = $this->service->getReportQueueSummary()->failed;

        $this->report($tenant, $user, ReportStatus::Failed);

        $result = $this->service->getReportQueueSummary();

        $this->assertSame($before + 1, $result->failed);
    }

    public function test_get_report_queue_summary_excludes_success(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $before = $this->service->getReportQueueSummary();

        $this->report($tenant, $user, ReportStatus::Success);

        $result = $this->service->getReportQueueSummary();

        $this->assertSame($before->pending, $result->pending);
        $this->assertSame($before->processing, $result->processing);
        $this->assertSame($before->failed, $result->failed);
    }

    public function test_get_migration_compliance_summary_returns_correct_type(): void
    {
        $result = $this->service->getMigrationComplianceSummary();

        $this->assertInstanceOf(MigrationComplianceSummaryData::class, $result);
    }

    public function test_get_migration_compliance_summary_total_equals_up_to_date_plus_behind(): void
    {
        $result = $this->service->getMigrationComplianceSummary();

        $this->assertSame($result->total, $result->upToDate + $result->behindCount);
    }

    public function test_get_migration_compliance_summary_behind_tenants_have_fewer_applied_than_available(): void
    {
        $result = $this->service->getMigrationComplianceSummary();

        foreach ($result->behind as $tenant) {
            $this->assertLessThan($tenant->available, $tenant->applied);
        }
    }

    public function test_get_migration_compliance_summary_behind_count_matches_behind_array_length(): void
    {
        $result = $this->service->getMigrationComplianceSummary();

        $this->assertCount($result->behindCount, $result->behind);
    }

    public function test_get_report_queue_summary_by_tenant_sorted_by_total_desc(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'aa']);
        $tenantB = Tenant::factory()->create(['slug' => 'bb']);
        $user = User::factory()->create();

        $this->report($tenantA, $user, ReportStatus::Failed);
        $this->report($tenantB, $user, ReportStatus::Pending);
        $this->report($tenantB, $user, ReportStatus::Failed);

        $result = $this->service->getReportQueueSummary();

        $byTenant = collect($result->byTenant)->keyBy('tenantId');
        $totalA = $byTenant[$tenantA->id]->pending + $byTenant[$tenantA->id]->processing + $byTenant[$tenantA->id]->failed;
        $totalB = $byTenant[$tenantB->id]->pending + $byTenant[$tenantB->id]->processing + $byTenant[$tenantB->id]->failed;
        $this->assertGreaterThan($totalA, $totalB);

        $orderedIds = collect($result->byTenant)->pluck('tenantId')->all();
        $posA = array_search($tenantA->id, $orderedIds);
        $posB = array_search($tenantB->id, $orderedIds);
        $this->assertLessThan($posA, $posB);
    }
}
