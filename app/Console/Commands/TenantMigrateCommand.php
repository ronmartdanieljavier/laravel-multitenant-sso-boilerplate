<?php

namespace App\Console\Commands;

use App\Data\Repositories\Central\TenantRepositoryData;
use App\Repositories\Central\TenantRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantMigrateCommand extends Command
{
    protected $signature = 'tenant:migrate
                            {--tenant= : The slug of a specific tenant to migrate}
                            {--fresh : Drop all tables and re-run all migrations}
                            {--seed : Seed the database after running migrations}
                            {--force : Force the operation to run in production}
                            {--rollback : Rollback the last migration batch}
                            {--step=0 : The number of migrations to be reverted (for rollback)}';

    protected $description = 'Run migrations for all tenant databases (or a specific tenant)';

    public function __construct(
        private TenantRepository $tenantRepository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenants = $this->tenantRepository->listActive($this->option('tenant') ?: null);

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found.');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($tenants as $tenant) {
            /** @var TenantRepositoryData $tenant */
            $this->info("Migrating tenant: {$tenant->name} ({$tenant->slug})");

            try {
                $this->configureTenantConnection($tenant);
                $this->runMigration();
                $this->syncMigrationVersions($tenant);
                $this->line('  <fg=green>✓</> Done');
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed: {$e->getMessage()}");
                $failed++;
            } finally {
                DB::purge('tenant');
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function configureTenantConnection(TenantRepositoryData $tenant): void
    {
        $config = [
            'driver' => 'pgsql',
            'host' => $tenant->dbHost,
            'port' => $tenant->dbPort,
            'database' => $tenant->dbName,
            'username' => $tenant->dbUsername,
            'password' => $tenant->dbPassword,
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ];

        Config::set('database.connections.tenant', $config);
        DB::purge('tenant');

        $this->ensureDatabaseExists($tenant, $config);

        DB::reconnect('tenant');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function ensureDatabaseExists(TenantRepositoryData $tenant, array $config): void
    {
        // Connect to the default maintenance DB to create the tenant DB if needed
        Config::set('database.connections.tenant_admin', array_merge($config, [
            'database' => 'postgres',
        ]));

        DB::purge('tenant_admin');

        $dbName = $tenant->dbName;

        if (! preg_match('/^[a-zA-Z0-9_-]+$/', (string) $dbName)) {
            throw new \RuntimeException("Invalid database name for tenant [{$tenant->slug}]: {$dbName}");
        }

        $exists = DB::connection('tenant_admin')
            ->selectOne('SELECT 1 FROM pg_database WHERE datname = ?', [$dbName]);

        if (! $exists) {
            DB::connection('tenant_admin')
                ->statement("CREATE DATABASE \"{$dbName}\"");

            $this->line("  Created database: {$dbName}");
        }

        DB::purge('tenant_admin');
        Config::set('database.connections.tenant_admin', null);
    }

    private function syncMigrationVersions(TenantRepositoryData $tenant): void
    {
        if (! Schema::connection('tenant')->hasTable('migrations')) {
            return;
        }

        $rows = DB::connection('tenant')
            ->table('migrations')
            ->orderBy('batch')
            ->orderBy('migration')
            ->get();

        foreach ($rows as $row) {
            $this->tenantRepository->syncMigrationVersion($tenant->id, $row->migration, $row->batch);
        }
    }

    private function runMigration(): void
    {
        $options = [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => $this->option('force'),
        ];

        if ($this->option('fresh')) {
            $this->call('migrate:fresh', array_merge($options, [
                '--seed' => $this->option('seed'),
            ]));

            return;
        }

        if ($this->option('rollback')) {
            $this->call('migrate:rollback', array_merge($options, [
                '--step' => $this->option('step') ?: 1,
            ]));

            return;
        }

        $this->call('migrate', array_merge($options, [
            '--seed' => $this->option('seed'),
        ]));
    }
}
