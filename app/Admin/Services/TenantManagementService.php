<?php

namespace App\Admin\Services;

use App\Admin\Data\CreateTenantData;
use App\Admin\Data\TenantData;
use App\Admin\Data\TenantHealthSummaryData;
use App\Admin\Data\UpdateTenantData;
use App\Data\Repositories\Central\TenantRepositoryData;
use App\Repositories\Central\ReportRepository;
use App\Repositories\Central\TenantRepository;
use App\Repositories\Central\UserRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;

class TenantManagementService
{
    public function __construct(
        protected TenantRepository $tenantRepository,
        protected ReportRepository $reportRepository,
        protected UserRepository $userRepository,
    ) {}

    /**
     * Get a list of all tenants with their details and health status.
     *
     * @return Collection<int, TenantData>
     */
    public function list(): Collection
    {
        $tenants = $this->tenantRepository->allWithMigrationVersions();
        $pendingByTenant = $this->reportRepository->pendingCountByTenant();
        $failedByTenant = $this->reportRepository->failedCountByTenant();
        $userCountByTenant = $this->tenantRepository->userCountByTenant();
        $loggedInByTenant = $this->tenantRepository->loggedInUserCountByTenant();

        return $tenants->map(function (TenantRepositoryData $tenant) use (
            $pendingByTenant,
            $failedByTenant,
            $userCountByTenant,
            $loggedInByTenant,
        ) {
            $versions = collect($tenant->migrationVersions);
            $lastMigration = $versions->max('migratedAt');
            $userCount = (int) ($userCountByTenant[$tenant->id] ?? 0);
            $loggedInCount = (int) ($loggedInByTenant[$tenant->id] ?? 0);
            $pendingReports = (int) ($pendingByTenant[$tenant->id] ?? 0);
            $failedReports = (int) ($failedByTenant[$tenant->id] ?? 0);

            return new TenantData(
                id: $tenant->id,
                name: $tenant->name,
                slug: $tenant->slug,
                isActive: $tenant->isActive,
                isMaintenance: $tenant->isMaintenance,
                dbHost: $tenant->dbHost,
                dbPort: $tenant->dbPort,
                dbName: $tenant->dbName,
                dbUsername: $tenant->dbUsername,
                isPasswordSet: $tenant->dbPassword !== null,
                hasReadReplica: $tenant->hasReadReplica,
                migrationCount: $versions->count(),
                userCount: $userCount,
                loggedInCount: $loggedInCount,
                pendingReports: $pendingReports,
                failedReports: $failedReports,
                healthStatus: $this->computeHealthStatus(
                    isActive: $tenant->isActive,
                    failedReports: $failedReports,
                    userCount: $userCount,
                    lastMigration: $lastMigration ? Carbon::parse($lastMigration) : null,
                ),
            );
        })->values();
    }

    /**
     * Get a summary of tenant health data.
     *
     * @param  Collection<int, TenantData>  $tenants
     */
    public function getSummary(Collection $tenants): TenantHealthSummaryData
    {
        return new TenantHealthSummaryData(
            total: $tenants->count(),
            healthy: $tenants->where('healthStatus', 'healthy')->count(),
            warning: $tenants->where('healthStatus', 'warning')->count(),
            critical: $tenants->where('healthStatus', 'critical')->count(),
            maintenance: $tenants->where('isMaintenance', true)->count(),
        );
    }

    /**
     * Create a new tenant.
     */
    public function create(CreateTenantData $data): TenantData
    {
        $tenant = $this->tenantRepository->create($data);

        Artisan::call('tenant:migrate', [
            '--tenant' => $tenant->slug,
            '--force' => true,
        ]);

        return $this->toData($this->tenantRepository->find($tenant->id));
    }

    /**
     * Update a tenant's information.
     */
    public function update(int $tenantId, UpdateTenantData $data): TenantData
    {
        return $this->toData($this->tenantRepository->update($tenantId, $data));
    }

    /**
     * Set the active status of a tenant.
     */
    public function setActive(int $tenantId, bool $isActive): void
    {
        if (! $isActive) {
            $userIds = $this->tenantRepository->getUserIdsForTenant($tenantId);
            $this->userRepository->revokeTokensForUsers($userIds);
        }

        $this->tenantRepository->setActive($tenantId, $isActive);
    }

    /**
     * Set the maintenance mode of a single tenant.
     * When enabling maintenance, all user tokens for that tenant are revoked.
     */
    public function setMaintenance(int $tenantId, bool $isMaintenance): void
    {
        if ($isMaintenance) {
            $userIds = $this->tenantRepository->getUserIdsForTenant($tenantId);
            $this->userRepository->revokeTokensForUsers($userIds);
        }

        $this->tenantRepository->setMaintenance($tenantId, $isMaintenance);
    }

    /**
     * Set the maintenance mode for all tenants.
     * When enabling, all user tokens for every tenant are revoked.
     */
    public function setMaintenanceAll(bool $isMaintenance): void
    {
        if ($isMaintenance) {
            $tenantIds = $this->tenantRepository->getAllIds();
            $allUserIds = $tenantIds->flatMap(
                fn (int $id) => $this->tenantRepository->getUserIdsForTenant($id)
            )->unique()->values();

            $this->userRepository->revokeTokensForUsers($allUserIds);
        }

        $this->tenantRepository->setMaintenanceAll($isMaintenance);
    }

    /**
     * Delete a tenant.
     */
    public function delete(int $tenantId): void
    {
        $tenant = $this->tenantRepository->find($tenantId);

        $userIds = $this->tenantRepository->getUserIdsForTenant($tenantId);
        $this->userRepository->revokeTokensForUsers($userIds);

        $this->tenantRepository->dropDatabase($tenant);
        $this->tenantRepository->delete($tenantId);
    }

    /**
     * Run database migrations for a specific tenant or all tenants.
     */
    public function runMigrations(?int $tenantId = null): void
    {
        $options = ['--force' => true];

        if ($tenantId !== null) {
            $tenant = $this->tenantRepository->find($tenantId);
            $options['--tenant'] = $tenant->slug;
        }

        Artisan::call('tenant:migrate', $options);
    }

    private function toData(TenantRepositoryData $tenant): TenantData
    {
        return new TenantData(
            id: $tenant->id,
            name: $tenant->name,
            slug: $tenant->slug,
            isActive: $tenant->isActive,
            isMaintenance: $tenant->isMaintenance,
            dbHost: $tenant->dbHost,
            dbPort: $tenant->dbPort,
            dbName: $tenant->dbName,
            dbUsername: $tenant->dbUsername,
            isPasswordSet: $tenant->dbPassword !== null,
            hasReadReplica: $tenant->hasReadReplica,
            migrationCount: count($tenant->migrationVersions),
            userCount: 0,
            loggedInCount: 0,
            pendingReports: 0,
            failedReports: 0,
            healthStatus: 'healthy',
        );
    }

    /**
     * Compute the health status of a tenant based on various metrics.
     */
    private function computeHealthStatus(
        bool $isActive,
        int $failedReports,
        int $userCount,
        ?Carbon $lastMigration,
    ): string {
        if (! $isActive || $failedReports > 0) {
            return 'critical';
        }

        $migrationStale = $lastMigration === null || $lastMigration->diffInDays(now()) > 30;

        if ($userCount === 0 || $migrationStale) {
            return 'warning';
        }

        return 'healthy';
    }
}
