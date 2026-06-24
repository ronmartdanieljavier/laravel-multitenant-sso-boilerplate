<?php

namespace App\Console\Commands;

use App\Data\Repositories\Central\TenantRepositoryData;
use App\Repositories\Central\TenantRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TenantMigrateStatusCommand extends Command
{
    protected $signature = 'tenant:migrate:status
                            {--tenant= : The slug of a specific tenant to check}';

    protected $description = 'Show the migration version status for all tenant databases';

    public function __construct(
        private TenantRepository $tenantRepository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenants = $this->tenantRepository->listActiveWithMigrationVersions($this->option('tenant') ?: null);

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found.');

            return self::SUCCESS;
        }

        $availableMigrations = $this->getAvailableMigrations();
        $totalMigrations = count($availableMigrations);

        $rows = [];

        foreach ($tenants as $tenant) {
            /** @var TenantRepositoryData $tenant */
            $versions = collect($tenant->migrationVersions);
            $appliedCount = $versions->count();
            $latest = $versions->sortByDesc('batch')->sortByDesc('migration')->first()?->migration;
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
