<?php

namespace App\Admin\Tests;

use App\Models\Central\App;
use App\Models\Central\Report;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMigrationVersion;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantHealthWebTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('admin.tenants'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_tenant_health_dashboard(): void
    {
        $user = User::factory()->create();
        Tenant::factory()->create(['name' => 'Acme Corp', 'slug' => 'acme']);

        $this->actingAs($user)
            ->get(route('admin.tenants'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Tenants/Index')
                ->has('tenants')
                ->has('summary')
            );
    }

    public function test_tenants_payload_includes_required_fields(): void
    {
        $user = User::factory()->create();
        Tenant::factory()->create(['slug' => 'acme']);

        $this->actingAs($user)
            ->get(route('admin.tenants'))
            ->assertInertia(fn ($page) => $page
                ->has('tenants.0', fn ($tenant) => $tenant
                    ->has('id')
                    ->has('name')
                    ->has('slug')
                    ->has('is_active')
                    ->has('has_read_replica')
                    ->has('user_count')
                    ->has('migration_count')
                    ->has('pending_reports')
                    ->has('failed_reports')
                    ->has('health_status')
                    ->etc()
                )
            );
    }

    public function test_summary_contains_all_status_counts(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.tenants'))
            ->assertInertia(fn ($page) => $page
                ->has('summary', fn ($summary) => $summary
                    ->has('total')
                    ->has('healthy')
                    ->has('warning')
                    ->has('critical')
                )
            );
    }

    public function test_inactive_tenant_appears_as_critical_in_response(): void
    {
        $user = User::factory()->create();
        Tenant::factory()->create(['is_active' => false, 'slug' => 'inactive-co']);

        $this->actingAs($user)
            ->get(route('admin.tenants'))
            ->assertInertia(fn ($page) => $page
                ->where('tenants', fn ($tenants) => collect($tenants)
                    ->firstWhere('slug', 'inactive-co')['health_status'] === 'critical'
                )
            );
    }

    public function test_tenant_with_failed_reports_appears_as_critical(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['is_active' => true, 'slug' => 'failing-co']);

        TenantMigrationVersion::create([
            'tenant_id' => $tenant->id,
            'migration' => '2026_01_01_create_test_table',
            'batch' => 1,
            'migrated_at' => now()->subDays(5),
        ]);
        DB::table('user_app_tenants')->insert([
            'user_id' => $user->id,
            'app_id' => App::factory()->create()->id,
            'tenant_id' => $tenant->id,
            'role' => 'user',
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Report::factory()->failed()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('admin.tenants'))
            ->assertInertia(fn ($page) => $page
                ->where('tenants', fn ($tenants) => collect($tenants)
                    ->firstWhere('slug', 'failing-co')['health_status'] === 'critical'
                )
            );
    }

    public function test_tenant_with_no_users_appears_as_warning(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['is_active' => true, 'slug' => 'empty-co']);

        TenantMigrationVersion::create([
            'tenant_id' => $tenant->id,
            'migration' => '2026_01_01_create_test_table',
            'batch' => 1,
            'migrated_at' => now()->subDays(5),
        ]);

        $this->actingAs($user)
            ->get(route('admin.tenants'))
            ->assertInertia(fn ($page) => $page
                ->where('tenants', fn ($tenants) => collect($tenants)
                    ->firstWhere('slug', 'empty-co')['health_status'] === 'warning'
                )
            );
    }

    public function test_tenant_with_stale_migrations_appears_as_warning(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['is_active' => true, 'slug' => 'stale-co']);

        TenantMigrationVersion::create([
            'tenant_id' => $tenant->id,
            'migration' => '2026_01_01_create_test_table',
            'batch' => 1,
            'migrated_at' => now()->subDays(40),
        ]);
        DB::table('user_app_tenants')->insert([
            'user_id' => $user->id,
            'app_id' => App::factory()->create()->id,
            'tenant_id' => $tenant->id,
            'role' => 'user',
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('admin.tenants'))
            ->assertInertia(fn ($page) => $page
                ->where('tenants', fn ($tenants) => collect($tenants)
                    ->firstWhere('slug', 'stale-co')['health_status'] === 'warning'
                )
            );
    }
}
