<?php

namespace App\TenantJobs\Services;

use App\Data\Repositories\Central\TenantJobRecordRepositoryData;
use App\Repositories\Central\TenantJobRecordRepository;
use App\TenantJobs\Data\TenantJobData;
use App\TenantJobs\Enums\TenantJobStatus;
use Illuminate\Pagination\LengthAwarePaginator;

class TenantJobsPortalService
{
    public function __construct(
        private readonly TenantJobRecordRepository $repository,
    ) {}

    /**
     * @return LengthAwarePaginator<int, TenantJobData>
     */
    public function listForTenant(
        int $tenantId,
        ?string $status = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $statusEnum = $status ? TenantJobStatus::tryFrom($status) : null;

        return $this->repository
            ->listForTenant($tenantId, $statusEnum, $perPage)
            ->through(fn (TenantJobRecordRepositoryData $dto) => $this->toData($dto));
    }

    private function toData(TenantJobRecordRepositoryData $dto): TenantJobData
    {
        $duration = null;

        if ($dto->startedAt !== null && $dto->finishedAt !== null) {
            $duration = (int) $dto->startedAt->diffInSeconds($dto->finishedAt);
        }

        return new TenantJobData(
            id: $dto->id,
            jobClass: $dto->jobClass,
            displayName: $dto->displayName,
            status: $dto->status,
            errorMessage: $dto->errorMessage,
            startedAt: $dto->startedAt,
            finishedAt: $dto->finishedAt,
            createdAt: $dto->createdAt,
            durationSeconds: $duration,
        );
    }
}
