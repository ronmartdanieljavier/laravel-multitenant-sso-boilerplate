<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

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

    public function handle(): int
    {
        $tenants = $this->resolveTenants();

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found.');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($tenants as $tenant) {
            $this->info("Migrating tenant: {$tenant->name} ({$tenant->slug})");

            try {
                $this->configureTenantConnection($tenant);
                $this->runMigration();
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

    private function resolveTenants()
    {
        $query = Tenant::where('is_active', true);

        if ($slug = $this->option('tenant')) {
            $query->where('slug', $slug);
        }

        return $query->get();
    }

    private function configureTenantConnection(Tenant $tenant): void
    {
        $config = [
            'driver' => 'pgsql',
            'host' => $tenant->db_host,
            'port' => $tenant->db_port,
            'database' => $tenant->db_name,
            'username' => $tenant->db_username,
            'password' => $tenant->db_password,
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

    private function ensureDatabaseExists(Tenant $tenant, array $config): void
    {
        // Connect to the default maintenance DB to create the tenant DB if needed
        Config::set('database.connections.tenant_admin', array_merge($config, [
            'database' => 'postgres',
        ]));

        DB::purge('tenant_admin');

        $dbName = $tenant->db_name;

        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $dbName)) {
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
