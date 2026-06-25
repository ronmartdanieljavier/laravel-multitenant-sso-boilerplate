<?php

namespace Tests\Feature\Reports;

use App\Http\Middleware\ResolveTenantDatabase;
use App\Models\Central\Report;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
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

    private function getWithTenant(User $user, Tenant $tenant): TestResponse
    {
        $this->withoutMiddleware(ResolveTenantDatabase::class);

        app()->bind(ResolveTenantDatabase::class, fn () => new class($tenant)
        {
            public function __construct(private Tenant $tenant) {}

            public function handle($request, $next)
            {
                $request->attributes->set('current_tenant', $this->tenant);

                return $next($request);
            }
        });

        return $this->actingAs($user)->get('/tenant/reports');
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->withoutMiddleware(ResolveTenantDatabase::class);

        $this->get('/tenant/reports')->assertRedirect(route('login'));
    }

    public function test_page_renders_for_authenticated_user_with_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $this->makeReport($tenant, $user);

        $this->getWithTenant($user, $tenant)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/ReportQueue')
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

        $this->getWithTenant($user, $tenant1)
            ->assertInertia(fn ($page) => $page->where('reports.total', 1));
    }

    public function test_reports_from_different_users_in_same_tenant_are_all_visible(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->makeReport($tenant, $user1);
        $this->makeReport($tenant, $user2);

        $this->getWithTenant($user1, $tenant)
            ->assertInertia(fn ($page) => $page->where('reports.total', 2));
    }

    public function test_reports_are_ordered_latest_first(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->makeReport($tenant, $user, ['created_at' => now()->subDay()]);
        $new = $this->makeReport($tenant, $user, ['created_at' => now()]);

        $this->getWithTenant($user, $tenant)
            ->assertInertia(fn ($page) => $page
                ->where('reports.data.0.id', $new->id)
            );
    }

    public function test_page_shows_all_statuses(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->makeReport($tenant, $user, ['status' => ReportStatus::Pending]);
        $this->makeReport($tenant, $user, ['status' => ReportStatus::Processing]);
        $this->makeReport($tenant, $user, ['status' => ReportStatus::Success]);
        $this->makeReport($tenant, $user, ['status' => ReportStatus::Failed]);

        $this->getWithTenant($user, $tenant)
            ->assertInertia(fn ($page) => $page->where('reports.total', 4));
    }
}
