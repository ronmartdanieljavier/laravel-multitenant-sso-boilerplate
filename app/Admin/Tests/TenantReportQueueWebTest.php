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

class TenantReportQueueWebTest extends TestCase
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

    public function test_unauthenticated_user_is_redirected(): void
    {
        $tenant = Tenant::factory()->create();

        $this->get(route('admin.tenants.reports', $tenant))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_report_queue(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $this->makeReport($tenant, $user);

        $this->actingAs($user)
            ->get(route('admin.tenants.reports', $tenant))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Tenants/ReportQueue')
                ->where('tenant.id', $tenant->id)
                ->has('reports')
            );
    }

    public function test_only_shows_reports_for_the_given_tenant(): void
    {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        $this->makeReport($tenant1, $user);
        $this->makeReport($tenant2, $user);

        $this->actingAs($user)
            ->get(route('admin.tenants.reports', $tenant1))
            ->assertInertia(fn ($page) => $page->where('reports.total', 1));
    }

    public function test_reports_from_all_users_in_tenant_are_visible(): void
    {
        $admin = User::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->makeReport($tenant, $user1);
        $this->makeReport($tenant, $user2);

        $this->actingAs($admin)
            ->get(route('admin.tenants.reports', $tenant))
            ->assertInertia(fn ($page) => $page->where('reports.total', 2));
    }

    public function test_reports_ordered_latest_first(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->makeReport($tenant, $user, ['created_at' => now()->subDay()]);
        $new = $this->makeReport($tenant, $user, ['created_at' => now()]);

        $this->actingAs($user)
            ->get(route('admin.tenants.reports', $tenant))
            ->assertInertia(fn ($page) => $page->where('reports.data.0.id', $new->id));
    }

    public function test_all_statuses_are_returned(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        foreach ([ReportStatus::Pending, ReportStatus::Processing, ReportStatus::Success, ReportStatus::Failed] as $status) {
            $this->makeReport($tenant, $user, ['status' => $status]);
        }

        $this->actingAs($user)
            ->get(route('admin.tenants.reports', $tenant))
            ->assertInertia(fn ($page) => $page->where('reports.total', 4));
    }
}
