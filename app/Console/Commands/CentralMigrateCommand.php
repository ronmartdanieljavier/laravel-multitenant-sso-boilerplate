<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CentralMigrateCommand extends Command
{
    protected $signature = 'central:migrate
                            {--fresh : Drop all tables and re-run all migrations}
                            {--seed : Seed the database after running migrations}
                            {--force : Force the operation to run in production}
                            {--rollback : Rollback the last migration batch}
                            {--step=0 : The number of migrations to be reverted (for rollback)}';

    protected $description = 'Run migrations only for the central database';

    public function handle(): int
    {
        $options = [
            '--path' => 'database/migrations/central',
            '--force' => $this->option('force'),
        ];

        if ($this->option('fresh')) {
            return $this->call('migrate:fresh', array_merge($options, [
                '--seed' => $this->option('seed'),
            ]));
        }

        if ($this->option('rollback')) {
            return $this->call('migrate:rollback', array_merge($options, [
                '--step' => $this->option('step') ?: 1,
            ]));
        }

        return $this->call('migrate', array_merge($options, [
            '--seed' => $this->option('seed'),
        ]));
    }
}
