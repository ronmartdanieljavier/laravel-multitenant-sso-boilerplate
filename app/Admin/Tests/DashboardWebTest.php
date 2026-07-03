<?php

namespace App\Admin\Tests;

use App\Models\Central\Tenant;
use App\Models\Central\TenantErrorLog;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardWebTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('admin'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());

        $this->actingAs($user)
            ->get(route('admin'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Index')
                ->has('stats')
                ->has('healthSummary')
                ->has('recentUsers')
                ->has('pendingUsers')
                ->has('unresolvedErrors')
                ->has('reportQueue')
                ->has('migrationCompliance')
            );
    }

    public function test_unresolved_errors_prop_contains_expected_keys(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());

        $this->actingAs($user)
            ->get(route('admin'))
            ->assertInertia(fn ($page) => $page
                ->has('unresolvedErrors.total')
                ->has('unresolvedErrors.error')
                ->has('unresolvedErrors.warning')
                ->has('unresolvedErrors.critical')
                ->has('unresolvedErrors.by_tenant')
            );
    }

    public function test_pending_users_prop_contains_only_users_with_invitation_token(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create(['is_active' => true]));
        $invited = User::factory()->create([
            'is_active' => false,
            'invitation_token' => Str::random(64),
            'invitation_sent_at' => now(),
        ]);
        $noToken = User::factory()->create(['is_active' => false, 'invitation_token' => null]);

        $this->actingAs($admin)
            ->get(route('admin'))
            ->assertInertia(fn ($page) => $page
                ->where('pendingUsers', function ($users) use ($invited, $noToken) {
                    $ids = collect($users)->pluck('id');

                    return $ids->contains($invited->id) && ! $ids->contains($noToken->id);
                })
            );
    }

    public function test_pending_users_excludes_active_users(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create(['is_active' => true]));

        $this->actingAs($admin)
            ->get(route('admin'))
            ->assertInertia(fn ($page) => $page
                ->where('pendingUsers', fn ($users) => collect($users)->every(fn ($u) => ! $u['is_active']))
            );
    }

    public function test_recent_users_contains_at_most_five_entries(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        User::factory()->count(10)->create();

        $this->actingAs($user)
            ->get(route('admin'))
            ->assertInertia(fn ($page) => $page
                ->where('recentUsers', fn ($users) => count($users) <= 5)
            );
    }

    public function test_recent_users_are_ordered_newest_first(): void
    {
        $oldest = $this->grantAdminRole(User::factory()->create(['email' => 'oldest@example.com', 'created_at' => now()->subDays(10)]));
        $newest = User::factory()->create(['email' => 'newest@example.com', 'created_at' => now()->addMinute()]);

        $this->actingAs($oldest)
            ->get(route('admin'))
            ->assertInertia(fn ($page) => $page
                ->where('recentUsers.0.email', 'newest@example.com')
            );
    }

    public function test_dashboard_stats_contains_expected_keys(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());

        $this->actingAs($user)
            ->get(route('admin'))
            ->assertInertia(fn ($page) => $page
                ->has('stats.total_users')
                ->has('stats.active_users')
                ->has('stats.pending_invitation_users')
                ->has('stats.active_apps')
                ->has('stats.active_tenants')
                ->has('stats.active_sso_sessions')
            );
    }

    public function test_dashboard_health_summary_contains_expected_keys(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());

        $this->actingAs($user)
            ->get(route('admin'))
            ->assertInertia(fn ($page) => $page
                ->has('healthSummary.total')
                ->has('healthSummary.healthy')
                ->has('healthSummary.warning')
                ->has('healthSummary.critical')
                ->has('healthSummary.maintenance')
            );
    }

    public function test_health_summary_total_equals_sum_of_statuses(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());

        $response = $this->actingAs($user)->get(route('admin'));
        $summary = $response->original->getData()['page']['props']['healthSummary'];

        $this->assertSame(
            $summary['total'],
            $summary['healthy'] + $summary['warning'] + $summary['critical'],
        );
    }

    public function test_migration_compliance_prop_contains_expected_keys(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());

        $this->actingAs($user)
            ->get(route('admin'))
            ->assertInertia(fn ($page) => $page
                ->has('migrationCompliance.total')
                ->has('migrationCompliance.up_to_date')
                ->has('migrationCompliance.behind_count')
                ->has('migrationCompliance.available_migrations')
                ->has('migrationCompliance.behind')
            );
    }

    public function test_migration_compliance_total_equals_up_to_date_plus_behind(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());

        $response = $this->actingAs($user)->get(route('admin'));
        $mc = $response->original->getData()['page']['props']['migrationCompliance'];

        $this->assertSame($mc['total'], $mc['up_to_date'] + $mc['behind_count']);
    }

    public function test_report_queue_prop_contains_expected_keys(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());

        $this->actingAs($user)
            ->get(route('admin'))
            ->assertInertia(fn ($page) => $page
                ->has('reportQueue.pending')
                ->has('reportQueue.processing')
                ->has('reportQueue.failed')
                ->has('reportQueue.by_tenant')
            );
    }

    public function test_unresolved_errors_total_reflects_real_error_log_rows(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $tenant = Tenant::factory()->create();

        $before = $this->actingAs($admin)->get(route('admin'))
            ->original->getData()['page']['props']['unresolvedErrors']['total'];

        TenantErrorLog::create([
            'tenant_id' => $tenant->id,
            'error_code' => 'E-WEB-'.strtoupper(substr(md5(uniqid()), 0, 8)),
            'exception_class' => 'RuntimeException',
            'message' => 'test',
            'severity' => 'error',
            'file' => '/app/foo.php',
            'line' => 1,
        ]);

        $after = $this->actingAs($admin)->get(route('admin'))
            ->original->getData()['page']['props']['unresolvedErrors']['total'];

        $this->assertSame($before + 1, $after);
    }

    public function test_unresolved_errors_total_excludes_resolved_logs(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $tenant = Tenant::factory()->create();

        $before = $this->actingAs($admin)->get(route('admin'))
            ->original->getData()['page']['props']['unresolvedErrors']['total'];

        TenantErrorLog::create([
            'tenant_id' => $tenant->id,
            'error_code' => 'E-WEB-'.strtoupper(substr(md5(uniqid()), 0, 8)),
            'exception_class' => 'RuntimeException',
            'message' => 'resolved test',
            'severity' => 'error',
            'file' => '/app/foo.php',
            'line' => 1,
            'resolved_at' => now(),
        ]);

        $after = $this->actingAs($admin)->get(route('admin'))
            ->original->getData()['page']['props']['unresolvedErrors']['total'];

        $this->assertSame($before, $after);
    }

    public function test_stats_active_sso_sessions_counts_personal_access_tokens(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());

        $before = $this->actingAs($admin)->get(route('admin'))
            ->original->getData()['page']['props']['stats']['active_sso_sessions'];

        $extra = User::factory()->create();
        $extra->createToken('test-token');

        $after = $this->actingAs($admin)->get(route('admin'))
            ->original->getData()['page']['props']['stats']['active_sso_sessions'];

        $this->assertSame($before + 1, $after);
    }

    public function test_dashboard_stats_are_numeric(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());

        $this->actingAs($user)
            ->get(route('admin'))
            ->assertInertia(fn ($page) => $page
                ->where('stats.total_users', fn ($v) => is_int($v))
                ->where('stats.active_apps', fn ($v) => is_int($v))
                ->where('stats.active_tenants', fn ($v) => is_int($v))
                ->where('stats.active_sso_sessions', fn ($v) => is_int($v))
            );
    }
}
