<?php

namespace Tests\Feature\Reports;

use App\Http\Middleware\ResolveWebTenantDatabase;
use App\Models\Central\Report;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportStatus;
use App\Reports\Jobs\GenerateReportJob;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TenantReportQueueWebTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
        ]);
    }

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
        $this->withoutMiddleware(ResolveWebTenantDatabase::class);

        app()->bind(ResolveWebTenantDatabase::class, fn () => new class($tenant)
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

    private function postWithTenant(User $user, Tenant $tenant, string $url, array $data = []): TestResponse
    {
        $this->withoutMiddleware(ResolveWebTenantDatabase::class);

        app()->bind(ResolveWebTenantDatabase::class, fn () => new class($tenant)
        {
            public function __construct(private Tenant $tenant) {}

            public function handle($request, $next)
            {
                $request->attributes->set('current_tenant', $this->tenant);

                return $next($request);
            }
        });

        return $this->actingAs($user)->post($url, $data);
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->withoutMiddleware(ResolveWebTenantDatabase::class);

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
                ->has('subscriptions')
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

    // ── Quick dispatch ────────────────────────────────────────────────────────

    public function test_quick_dispatch_creates_report_and_queues_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->postWithTenant($user, $tenant, '/tenant/reports/quick', [
            'type' => 'documents_summary',
            'format' => 'screen',
        ])->assertRedirect();

        $this->assertDatabaseHas('reports', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'type' => 'documents_summary',
            'format' => 'screen',
            'status' => 'pending',
        ]);

        Queue::assertPushed(GenerateReportJob::class);
    }

    public function test_quick_dispatch_validates_required_fields(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->postWithTenant($user, $tenant, '/tenant/reports/quick', [])
            ->assertSessionHasErrors(['type', 'format']);
    }

    public function test_quick_dispatch_validates_format_enum(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->postWithTenant($user, $tenant, '/tenant/reports/quick', [
            'type' => 'documents_summary',
            'format' => 'invalid_format',
        ])->assertSessionHasErrors(['format']);
    }

    public function test_quick_dispatch_returns_flash_success(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->postWithTenant($user, $tenant, '/tenant/reports/quick', [
            'type' => 'documents_summary',
            'format' => 'pdf',
        ])->assertSessionHas('success');
    }

    public function test_unauthenticated_quick_dispatch_is_rejected(): void
    {
        $this->withoutMiddleware(ResolveWebTenantDatabase::class);

        $this->post('/tenant/reports/quick', [
            'type' => 'documents_summary',
            'format' => 'screen',
        ])->assertRedirect(route('login'));
    }

    // ── Retry ─────────────────────────────────────────────────────────────────

    public function test_retry_resets_failed_report_and_queues_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $report = $this->makeReport($tenant, $user, [
            'status' => ReportStatus::Failed,
            'error_message' => 'Something went wrong',
        ]);

        $this->postWithTenant($user, $tenant, "/tenant/reports/{$report->id}/retry")
            ->assertRedirect()
            ->assertSessionHas('success');

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

        $this->postWithTenant($user, $tenant1, "/tenant/reports/{$report->id}/retry")
            ->assertForbidden();

        Queue::assertNothingPushed();
    }
}
