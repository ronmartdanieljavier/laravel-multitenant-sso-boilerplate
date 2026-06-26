<?php

namespace App\Tenant\Tests;

use App\Models\Central\Report;
use App\Models\Central\Tenant;
use App\Models\Central\TenantErrorLog;
use App\Models\Central\User;
use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\WithFakeTenantContext;
use Tests\TestCase;

class TenantDashboardWebTest extends TestCase
{
    use LazilyRefreshDatabase, WithFakeTenantContext;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
        ]);
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
            'error_code' => 'E-WEB-'.fake()->unique()->regexify('[A-Z0-9]{8}'),
            'exception_class' => 'RuntimeException',
            'message' => 'Test error',
            'severity' => $severity,
        ]);
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'web-unauth']);
        $this->setFakeTenant($tenant);

        $this->get(route('tenant'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'web-view']);
        $this->setFakeTenant($tenant);

        $this->actingAs($user)
            ->get(route('tenant'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Index')
                ->has('stats')
                ->has('recentReports')
                ->has('recentErrors')
                ->has('recentDocuments')
            );
    }

    public function test_dashboard_stats_reflect_tenant_reports(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'web-stats']);
        $this->makeReport($tenant, $user, ReportStatus::Pending);
        $this->makeReport($tenant, $user, ReportStatus::Failed);
        $this->setFakeTenant($tenant);

        $this->actingAs($user)
            ->get(route('tenant'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.pending_reports', 1)
                ->where('stats.failed_reports', 1)
            );
    }

    public function test_dashboard_stats_reflect_tenant_error_counts(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'web-errs']);
        $this->makeErrorLog($tenant, 'error');
        $this->makeErrorLog($tenant, 'critical');
        $this->setFakeTenant($tenant);

        $this->actingAs($user)
            ->get(route('tenant'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.unresolved_errors', 2)
                ->where('stats.critical_errors', 1)
            );
    }

    public function test_recent_reports_are_scoped_to_tenant(): void
    {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create(['slug' => 'web-scope1']);
        $tenant2 = Tenant::factory()->create(['slug' => 'web-scope2']);
        $this->makeReport($tenant1, $user);
        $this->makeReport($tenant2, $user);
        $this->setFakeTenant($tenant1);

        $this->actingAs($user)
            ->get(route('tenant'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('recentReports', 1));
    }

    public function test_recent_errors_only_include_unresolved(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'web-unres']);
        $this->makeErrorLog($tenant);
        TenantErrorLog::create([
            'tenant_id' => $tenant->id,
            'error_code' => 'E-RES-RESOLVED1',
            'exception_class' => 'RuntimeException',
            'message' => 'Resolved error',
            'severity' => 'error',
            'resolved_at' => now(),
        ]);
        $this->setFakeTenant($tenant);

        $this->actingAs($user)
            ->get(route('tenant'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('recentErrors', 1));
    }

    public function test_dashboard_shows_all_zeros_with_no_activity(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'web-zero']);
        $this->setFakeTenant($tenant);

        $this->actingAs($user)
            ->get(route('tenant'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.pending_reports', 0)
                ->where('stats.failed_reports', 0)
                ->where('stats.unresolved_errors', 0)
                ->where('stats.total_documents', 0)
                ->has('recentReports', 0)
                ->has('recentErrors', 0)
                ->has('recentDocuments', 0)
            );
    }

    public function test_dashboard_recent_reports_capped_at_five(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'web-cap']);
        for ($i = 0; $i < 8; $i++) {
            $this->makeReport($tenant, $user);
        }
        $this->setFakeTenant($tenant);

        $this->actingAs($user)
            ->get(route('tenant'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where(
                'recentReports',
                fn ($reports) => count($reports) <= 5,
            ));
    }

    public function test_dashboard_stats_reflect_processing_reports(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'web-proc']);
        $this->makeReport($tenant, $user, ReportStatus::Processing);
        $this->setFakeTenant($tenant);

        $this->actingAs($user)
            ->get(route('tenant'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('stats.processing_reports', 1));
    }
}
