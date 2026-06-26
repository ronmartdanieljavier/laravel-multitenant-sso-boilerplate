<?php

namespace App\TenantErrors\Services;

use App\Data\Repositories\Central\TenantErrorLogRepositoryData;
use App\Repositories\Central\TenantErrorLogRepository;
use App\TenantErrors\Data\TenantErrorData;
use Illuminate\Support\Collection;

class TenantErrorsPortalService
{
    public function __construct(
        private readonly TenantErrorLogRepository $repository,
    ) {}

    /**
     * @return Collection<int, TenantErrorData>
     */
    public function listForTenant(int $tenantId, ?string $severity = null, bool $unresolvedOnly = false): Collection
    {
        return $this->repository
            ->listForTenant($tenantId, $severity, $unresolvedOnly)
            ->map(fn (TenantErrorLogRepositoryData $dto) => $this->toData($dto));
    }

    public function getForTenant(int $tenantId, int $id): TenantErrorData
    {
        $dto = $this->repository->findById($id);

        abort_if($dto->tenantId !== $tenantId, 404);

        return $this->toData($dto);
    }

    private function toData(TenantErrorLogRepositoryData $dto): TenantErrorData
    {
        return new TenantErrorData(
            id: $dto->id,
            errorCode: $dto->errorCode,
            exceptionClass: $dto->exceptionClass,
            message: $dto->message,
            severity: $dto->severity,
            resolved: $dto->resolvedAt !== null,
            resolvedAt: $dto->resolvedAt,
            createdAt: $dto->createdAt,
            requestUrl: $dto->requestUrl,
            requestMethod: $dto->requestMethod,
            requestParams: $dto->requestParams,
            context: $dto->context,
        );
    }
}
