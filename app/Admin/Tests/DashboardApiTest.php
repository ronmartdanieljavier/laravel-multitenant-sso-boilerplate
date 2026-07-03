<?php

namespace App\Admin\Tests;

use App\Models\Central\Tenant;
use App\Models\Central\TenantErrorLog;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson(route('admin.api.dashboard'))->assertUnauthorized();
    }

    public function test_authenticated_user_can_retrieve_dashboard_stats(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());

        $this->actingAs($user, 'sanctum')
            ->getJson(route('admin.api.dashboard'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_users',
                    'active_users',
                    'pending_invitation_users',
                    'active_apps',
                    'active_tenants',
                    'active_sso_sessions',
                ],
                'health_summary' => ['total', 'healthy', 'warning', 'critical', 'maintenance'],
                'recent_users',
                'pending_users',
                'unresolved_errors' => ['total', 'error', 'warning', 'critical', 'by_tenant'],
                'report_queue' => ['pending', 'processing', 'failed', 'by_tenant'],
                'migration_compliance' => ['total', 'up_to_date', 'behind_count', 'available_migrations', 'behind'],
            ]);
    }

    public function test_all_stat_values_are_integers(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());

        $data = $this->actingAs($user, 'sanctum')
            ->getJson(route('admin.api.dashboard'))
            ->assertOk()
            ->json('data');

        $this->assertIsInt($data['total_users']);
        $this->assertIsInt($data['active_users']);
        $this->assertIsInt($data['pending_invitation_users']);
        $this->assertIsInt($data['active_apps']);
        $this->assertIsInt($data['active_tenants']);
        $this->assertIsInt($data['active_sso_sessions']);
    }

    public function test_health_summary_values_are_integers(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());

        $summary = $this->actingAs($user, 'sanctum')
            ->getJson(route('admin.api.dashboard'))
            ->assertOk()
            ->json('health_summary');

        $this->assertIsInt($summary['total']);
        $this->assertIsInt($summary['healthy']);
        $this->assertIsInt($summary['warning']);
        $this->assertIsInt($summary['critical']);
        $this->assertIsInt($summary['maintenance']);
    }

    public function test_health_summary_total_equals_sum_of_statuses(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());

        $summary = $this->actingAs($user, 'sanctum')
            ->getJson(route('admin.api.dashboard'))
            ->assertOk()
            ->json('health_summary');

        $this->assertSame($summary['total'], $summary['healthy'] + $summary['warning'] + $summary['critical']);
    }

    public function test_pending_users_excludes_users_without_invitation_token(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $noToken = User::factory()->create(['is_active' => false, 'invitation_token' => null]);
        $invited = User::factory()->create([
            'is_active' => false,
            'invitation_token' => Str::random(64),
            'invitation_sent_at' => now(),
        ]);

        $pending = $this->actingAs($admin, 'sanctum')
            ->getJson(route('admin.api.dashboard'))
            ->assertOk()
            ->json('pending_users');

        $ids = collect($pending)->pluck('id');
        $this->assertTrue($ids->contains($invited->id));
        $this->assertFalse($ids->contains($noToken->id));
    }

    public function test_recent_users_contains_at_most_five_entries(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        User::factory()->count(10)->create();

        $recent = $this->actingAs($user, 'sanctum')
            ->getJson(route('admin.api.dashboard'))
            ->assertOk()
            ->json('recent_users');

        $this->assertLessThanOrEqual(5, count($recent));
    }

    public function test_recent_users_are_ordered_newest_first(): void
    {
        $oldest = $this->grantAdminRole(User::factory()->create(['email' => 'api-oldest@example.com', 'created_at' => now()->subDays(10)]));
        User::factory()->create(['email' => 'api-newest@example.com', 'created_at' => now()->addMinute()]);

        $recent = $this->actingAs($oldest, 'sanctum')
            ->getJson(route('admin.api.dashboard'))
            ->assertOk()
            ->json('recent_users');

        $this->assertSame('api-newest@example.com', $recent[0]['email']);
    }

    public function test_unresolved_errors_values_are_integers(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());

        $errors = $this->actingAs($user, 'sanctum')
            ->getJson(route('admin.api.dashboard'))
            ->assertOk()
            ->json('unresolved_errors');

        $this->assertIsInt($errors['total']);
        $this->assertIsInt($errors['error']);
        $this->assertIsInt($errors['warning']);
        $this->assertIsInt($errors['critical']);
    }

    public function test_unresolved_errors_total_reflects_real_error_log_rows(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $tenant = Tenant::factory()->create();

        $before = $this->actingAs($admin, 'sanctum')
            ->getJson(route('admin.api.dashboard'))
            ->assertOk()
            ->json('unresolved_errors.total');

        TenantErrorLog::create([
            'tenant_id' => $tenant->id,
            'error_code' => 'E-API-'.strtoupper(substr(md5(uniqid()), 0, 8)),
            'exception_class' => 'RuntimeException',
            'message' => 'test',
            'severity' => 'critical',
            'file' => '/app/foo.php',
            'line' => 1,
        ]);

        $after = $this->actingAs($admin, 'sanctum')
            ->getJson(route('admin.api.dashboard'))
            ->assertOk()
            ->json('unresolved_errors.total');

        $this->assertSame($before + 1, $after);
    }

    public function test_unresolved_errors_excludes_resolved_logs(): void
    {
        $admin = $this->grantAdminRole(User::factory()->create());
        $tenant = Tenant::factory()->create();

        $before = $this->actingAs($admin, 'sanctum')
            ->getJson(route('admin.api.dashboard'))
            ->assertOk()
            ->json('unresolved_errors.total');

        TenantErrorLog::create([
            'tenant_id' => $tenant->id,
            'error_code' => 'E-API-'.strtoupper(substr(md5(uniqid()), 0, 8)),
            'exception_class' => 'RuntimeException',
            'message' => 'resolved',
            'severity' => 'error',
            'file' => '/app/foo.php',
            'line' => 1,
            'resolved_at' => now(),
        ]);

        $after = $this->actingAs($admin, 'sanctum')
            ->getJson(route('admin.api.dashboard'))
            ->assertOk()
            ->json('unresolved_errors.total');

        $this->assertSame($before, $after);
    }

    public function test_report_queue_values_are_integers(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());

        $queue = $this->actingAs($user, 'sanctum')
            ->getJson(route('admin.api.dashboard'))
            ->assertOk()
            ->json('report_queue');

        $this->assertIsInt($queue['pending']);
        $this->assertIsInt($queue['processing']);
        $this->assertIsInt($queue['failed']);
    }

    public function test_migration_compliance_values_are_integers(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());

        $mc = $this->actingAs($user, 'sanctum')
            ->getJson(route('admin.api.dashboard'))
            ->assertOk()
            ->json('migration_compliance');

        $this->assertIsInt($mc['total']);
        $this->assertIsInt($mc['up_to_date']);
        $this->assertIsInt($mc['behind_count']);
        $this->assertIsInt($mc['available_migrations']);
    }

    public function test_migration_compliance_total_equals_up_to_date_plus_behind(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());

        $mc = $this->actingAs($user, 'sanctum')
            ->getJson(route('admin.api.dashboard'))
            ->assertOk()
            ->json('migration_compliance');

        $this->assertSame($mc['total'], $mc['up_to_date'] + $mc['behind_count']);
    }
}
