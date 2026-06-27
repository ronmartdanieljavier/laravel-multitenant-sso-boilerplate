<?php

namespace App\Admin\Services;

use App\Admin\Data\AdminJobData;
use App\Data\Repositories\Central\TenantJobRecordRepositoryData;
use App\Repositories\Central\TenantJobRecordRepository;
use App\Repositories\Central\TenantRepository;
use App\TenantJobs\Data\TenantJobData;
use App\TenantJobs\Enums\TenantJobStatus;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TenantJobsAdminService
{
    public function __construct(
        private readonly TenantJobRecordRepository $repository,
        private readonly TenantRepository $tenantRepository,
    ) {}

    /**
     * @return LengthAwarePaginator<int, AdminJobData>
     */
    public function listAll(
        int $perPage = 20,
        ?int $tenantId = null,
        ?TenantJobStatus $status = null,
    ): LengthAwarePaginator {
        $tenantNames = $this->tenantRepository->pluckNames();

        return $this->repository
            ->listAll($tenantId, $status, $perPage)
            ->through(fn (TenantJobRecordRepositoryData $dto) => $this->toAdminData($dto, $tenantNames));
    }

    /**
     * @return LengthAwarePaginator<int, TenantJobData>
     */
    public function listForTenant(
        int $tenantId,
        ?TenantJobStatus $status = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return $this->repository
            ->listForTenant($tenantId, $status, $perPage)
            ->through(fn (TenantJobRecordRepositoryData $dto) => $this->toData($dto));
    }

    private function toAdminData(TenantJobRecordRepositoryData $dto, Collection $tenantNames): AdminJobData
    {
        return new AdminJobData(
            id: $dto->id,
            tenantId: $dto->tenantId,
            tenantName: $tenantNames->get($dto->tenantId, 'Unknown'),
            jobClass: $dto->jobClass,
            displayName: $dto->displayName,
            status: $dto->status,
            errorMessage: $dto->errorMessage,
            startedAt: $dto->startedAt,
            finishedAt: $dto->finishedAt,
            createdAt: $dto->createdAt,
            durationSeconds: $this->computeDuration($dto),
        );
    }

    private function toData(TenantJobRecordRepositoryData $dto): TenantJobData
    {
        return new TenantJobData(
            id: $dto->id,
            jobClass: $dto->jobClass,
            displayName: $dto->displayName,
            status: $dto->status,
            errorMessage: $dto->errorMessage,
            startedAt: $dto->startedAt,
            finishedAt: $dto->finishedAt,
            createdAt: $dto->createdAt,
            durationSeconds: $this->computeDuration($dto),
        );
    }

    private function computeDuration(TenantJobRecordRepositoryData $dto): ?int
    {
        if ($dto->startedAt === null || $dto->finishedAt === null) {
            return null;
        }

        return (int) $dto->startedAt->diffInSeconds($dto->finishedAt);
    }
}
