<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TenantMigrateStatusCommand extends Command
{
    protected $signature = 'tenant:migrate:status
                            {--tenant= : The slug of a specific tenant to check}';

    protected $description = 'Show the migration version status for all tenant databases';

    public function handle(): int
    {
        $tenants = $this->resolveTenants();

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found.');

            return self::SUCCESS;
        }

        $availableMigrations = $this->getAvailableMigrations();
        $totalMigrations = count($availableMigrations);

        $rows = [];

        foreach ($tenants as $tenant) {
            $applied = $tenant->migrationVersions()->pluck('migration');
            $appliedCount = $applied->count();
            $latest = $tenant->migrationVersions()->orderByDesc('batch')->orderByDesc('migration')->value('migration');
            $upToDate = $appliedCount === $totalMigrations;

            $rows[] = [
                $tenant->name,
                $tenant->slug,
                "{$appliedCount}/{$totalMigrations}",
                $upToDate ? '<fg=green>Yes</>' : '<fg=yellow>No</>',
                $latest ? basename($latest) : '<fg=gray>—</>',
            ];
        }

        $this->table(
            ['Tenant', 'Slug', 'Applied', 'Up to Date', 'Latest Migration'],
            $rows,
        );

        return self::SUCCESS;
    }

    private function resolveTenants()
    {
        $query = Tenant::with('migrationVersions')->where('is_active', true);

        if ($slug = $this->option('tenant')) {
            $query->where('slug', $slug);
        }

        return $query->get();
    }

    /** @return string[] */
    private function getAvailableMigrations(): array
    {
        return collect(File::files(database_path('migrations/tenant')))
            ->map(fn ($file) => $file->getFilenameWithoutExtension())
            ->sort()
            ->values()
            ->all();
    }
}
