<?php

namespace App\Admin\Tests;

use App\Admin\Data\TenantHealthData;
use App\Admin\Data\TenantHealthSummaryData;
use App\Admin\Services\TenantHealthService;
use App\Models\Central\App;
use App\Models\Central\Report;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMigrationVersion;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantHealthServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private TenantHealthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TenantHealthService::class);
    }

    public function test_get_tenants_returns_collection_of_tenant_health_data(): void
    {
        Tenant::factory()->create(['name' => 'Acme Corp', 'slug' => 'acme']);

        $tenants = $this->service->getTenants();

        $acme = $tenants->firstWhere('slug', 'acme');
        $this->assertInstanceOf(TenantHealthData::class, $acme);
        $this->assertSame('Acme Corp', $acme->name);
        $this->assertSame('acme', $acme->slug);
    }

    public function test_inactive_tenant_is_marked_critical(): void
    {
        Tenant::factory()->create(['is_active' => false, 'slug' => 'inactive-co']);

        $tenants = $this->service->getTenants();

        $this->assertSame('critical', $tenants->firstWhere('slug', 'inactive-co')->healthStatus);
    }

    public function test_tenant_with_failed_reports_is_marked_critical(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['is_active' => true, 'slug' => 'failing-co']);
        $this->seedTenantWithUserAndMigration($tenant, $user, now()->subDays(5));
        Report::factory()->failed()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);

        $tenants = $this->service->getTenants();

        $this->assertSame('critical', $tenants->firstWhere('slug', 'failing-co')->healthStatus);
    }

    public function test_tenant_with_no_users_is_marked_warning(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true, 'slug' => 'empty-co']);
        TenantMigrationVersion::create([
            'tenant_id' => $tenant->id,
            'migration' => '2026_01_01_create_test_table',
            'batch' => 1,
            'migrated_at' => now()->subDays(5),
        ]);

        $tenants = $this->service->getTenants();

        $this->assertSame('warning', $tenants->firstWhere('slug', 'empty-co')->healthStatus);
    }

    public function test_tenant_with_stale_migrations_is_marked_warning(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['is_active' => true, 'slug' => 'stale-co']);
        $this->seedTenantWithUserAndMigration($tenant, $user, now()->subDays(40));

        $tenants = $this->service->getTenants();

        $this->assertSame('warning', $tenants->firstWhere('slug', 'stale-co')->healthStatus);
    }

    public function test_healthy_tenant_is_marked_healthy(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['is_active' => true, 'slug' => 'healthy-co']);
        $this->seedTenantWithUserAndMigration($tenant, $user, now()->subDays(5));

        $tenants = $this->service->getTenants();

        $this->assertSame('healthy', $tenants->firstWhere('slug', 'healthy-co')->healthStatus);
    }

    public function test_tenant_user_count_is_correct(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'counted-co']);
        $app = App::factory()->create();

        foreach ([$userA, $userB] as $user) {
            DB::table('user_app_tenants')->insert([
                'user_id' => $user->id,
                'app_id' => $app->id,
                'tenant_id' => $tenant->id,
                'role' => 'user',
                'is_default' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $tenants = $this->service->getTenants();

        $this->assertSame(2, $tenants->firstWhere('slug', 'counted-co')->userCount);
    }

    public function test_get_summary_counts_statuses_correctly(): void
    {
        $healthy = new TenantHealthData(1, 'A', 'a', true, false, 1, 1, null, 0, 0, null, 'healthy');
        $warning = new TenantHealthData(2, 'B', 'b', true, false, 0, 0, null, 0, 0, null, 'warning');
        $critical = new TenantHealthData(3, 'C', 'c', false, false, 0, 0, null, 0, 0, null, 'critical');

        $summary = $this->service->getSummary(collect([$healthy, $warning, $critical]));

        $this->assertInstanceOf(TenantHealthSummaryData::class, $summary);
        $this->assertSame(3, $summary->total);
        $this->assertSame(1, $summary->healthy);
        $this->assertSame(1, $summary->warning);
        $this->assertSame(1, $summary->critical);
    }

    public function test_compute_health_status_returns_critical_for_inactive(): void
    {
        $status = $this->service->computeHealthStatus(
            isActive: false,
            failedReports: 0,
            userCount: 5,
            lastMigration: now()->subDays(1),
        );

        $this->assertSame('critical', $status);
    }

    public function test_compute_health_status_returns_critical_for_failed_reports(): void
    {
        $status = $this->service->computeHealthStatus(
            isActive: true,
            failedReports: 1,
            userCount: 5,
            lastMigration: now()->subDays(1),
        );

        $this->assertSame('critical', $status);
    }

    public function test_compute_health_status_returns_warning_for_no_users(): void
    {
        $status = $this->service->computeHealthStatus(
            isActive: true,
            failedReports: 0,
            userCount: 0,
            lastMigration: now()->subDays(1),
        );

        $this->assertSame('warning', $status);
    }

    public function test_compute_health_status_returns_warning_for_stale_migration(): void
    {
        $status = $this->service->computeHealthStatus(
            isActive: true,
            failedReports: 0,
            userCount: 5,
            lastMigration: now()->subDays(31),
        );

        $this->assertSame('warning', $status);
    }

    public function test_compute_health_status_returns_warning_when_no_migration(): void
    {
        $status = $this->service->computeHealthStatus(
            isActive: true,
            failedReports: 0,
            userCount: 5,
            lastMigration: null,
        );

        $this->assertSame('warning', $status);
    }

    public function test_compute_health_status_returns_healthy(): void
    {
        $status = $this->service->computeHealthStatus(
            isActive: true,
            failedReports: 0,
            userCount: 5,
            lastMigration: now()->subDays(5),
        );

        $this->assertSame('healthy', $status);
    }

    private function seedTenantWithUserAndMigration(Tenant $tenant, User $user, Carbon $migratedAt): void
    {
        TenantMigrationVersion::create([
            'tenant_id' => $tenant->id,
            'migration' => '2026_01_01_create_test_table',
            'batch' => 1,
            'migrated_at' => $migratedAt,
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
    }
}
