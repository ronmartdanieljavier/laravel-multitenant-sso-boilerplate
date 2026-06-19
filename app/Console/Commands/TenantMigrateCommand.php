<?php

namespace App\Console\Commands;

use App\Auth\Models\Tenant;
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
        Config::set('database.connections.tenant', [
            'driver' => 'mysql',
            'host' => $tenant->db_host,
            'port' => $tenant->db_port,
            'database' => $tenant->db_name,
            'username' => $tenant->db_username,
            'password' => $tenant->db_password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]);

        DB::purge('tenant');
        DB::reconnect('tenant');
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
