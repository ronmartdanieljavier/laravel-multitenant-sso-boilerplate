<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Central\TenantMigrationVersion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TenantMigrationVersionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_tenant_migration_version_can_be_created(): void
    {
        $tenant = Tenant::factory()->create();

        $version = TenantMigrationVersion::create([
            'tenant_id' => $tenant->id,
            'migration' => '2026_06_19_104351_create_companies_table',
            'batch' => 1,
            'migrated_at' => now(),
        ]);

        $this->assertDatabaseHas('tenant_migration_versions', [
            'tenant_id' => $tenant->id,
            'migration' => '2026_06_19_104351_create_companies_table',
            'batch' => 1,
        ]);

        $this->assertEquals($tenant->id, $version->tenant->id);
    }

    public function test_tenant_migration_versions_deleted_when_tenant_deleted(): void
    {
        $tenant = Tenant::factory()->create();

        TenantMigrationVersion::create([
            'tenant_id' => $tenant->id,
            'migration' => '2026_06_19_104351_create_companies_table',
            'batch' => 1,
            'migrated_at' => now(),
        ]);

        $tenant->delete();

        $this->assertDatabaseMissing('tenant_migration_versions', [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_tenant_has_migration_versions_relationship(): void
    {
        $tenant = Tenant::factory()->create();

        TenantMigrationVersion::create([
            'tenant_id' => $tenant->id,
            'migration' => '2026_06_19_104351_create_companies_table',
            'batch' => 1,
            'migrated_at' => now(),
        ]);

        TenantMigrationVersion::create([
            'tenant_id' => $tenant->id,
            'migration' => '2026_06_19_104352_create_properties_table',
            'batch' => 1,
            'migrated_at' => now(),
        ]);

        $this->assertCount(2, $tenant->migrationVersions);
    }

    public function test_migration_version_enforces_unique_tenant_migration_pair(): void
    {
        $tenant = Tenant::factory()->create();

        TenantMigrationVersion::create([
            'tenant_id' => $tenant->id,
            'migration' => '2026_06_19_104351_create_companies_table',
            'batch' => 1,
            'migrated_at' => now(),
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        TenantMigrationVersion::create([
            'tenant_id' => $tenant->id,
            'migration' => '2026_06_19_104351_create_companies_table',
            'batch' => 2,
            'migrated_at' => now(),
        ]);
    }

    public function test_migrate_status_command_shows_tenant_table(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Acme Corp', 'slug' => 'acme']);

        TenantMigrationVersion::create([
            'tenant_id' => $tenant->id,
            'migration' => '2026_06_19_104351_create_companies_table',
            'batch' => 1,
            'migrated_at' => now(),
        ]);

        $this->artisan('tenant:migrate:status')
            ->assertSuccessful()
            ->expectsOutputToContain('Acme Corp');
    }

    public function test_migrate_status_command_filters_by_tenant_slug(): void
    {
        $tenantA = Tenant::factory()->create(['name' => 'Acme Corp', 'slug' => 'acme']);
        $tenantB = Tenant::factory()->create(['name' => 'Beta Inc', 'slug' => 'beta']);

        $this->artisan('tenant:migrate:status --tenant=acme')
            ->assertSuccessful()
            ->expectsOutputToContain('Acme Corp')
            ->doesntExpectOutputToContain('Beta Inc');
    }

    public function test_migrate_status_command_warns_when_no_tenants_found(): void
    {
        $this->artisan('tenant:migrate:status --tenant=nonexistent')
            ->assertSuccessful()
            ->expectsOutputToContain('No active tenants found');
    }

    public function test_migrate_status_command_shows_up_to_date_for_fully_migrated_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $migrations = glob(database_path('migrations/tenant/*.php'));

        foreach ($migrations as $i => $file) {
            TenantMigrationVersion::create([
                'tenant_id' => $tenant->id,
                'migration' => pathinfo($file, PATHINFO_FILENAME),
                'batch' => 1,
                'migrated_at' => now(),
            ]);
        }

        $totalMigrations = count($migrations);

        $this->artisan('tenant:migrate:status')
            ->assertSuccessful()
            ->expectsOutputToContain("{$totalMigrations}/{$totalMigrations}");
    }
}
