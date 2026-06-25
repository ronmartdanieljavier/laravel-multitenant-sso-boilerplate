<?php

namespace App\Admin\Services;

use App\Admin\Data\TenantErrorLogData;
use App\Data\Repositories\Central\TenantErrorLogRepositoryData;
use App\Models\Central\Tenant;
use App\Repositories\Central\TenantErrorLogRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Throwable;

class TenantErrorLogService
{
    public function __construct(
        private readonly TenantErrorLogRepository $repository,
    ) {}

    /**
     * Record an exception that occurred in a tenant request context.
     */
    public function record(
        Throwable $exception,
        Tenant $tenant,
        Request $request,
        string $severity = 'error',
    ): TenantErrorLogData {
        $errorCode = $this->repository->generateErrorCode($tenant->slug);

        $params = array_merge(
            $request->query->all(),
            $request->request->all(),
        );

        $headers = collect($request->headers->all())
            ->map(fn (array $v) => implode(', ', $v))
            ->all();

        $log = $this->repository->create([
            'tenant_id' => $tenant->id,
            'user_id' => $request->user()?->id,
            'error_code' => $errorCode,
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $this->formatTrace($exception),
            'request_url' => $request->fullUrl(),
            'request_method' => $request->method(),
            'request_params' => $this->repository->sanitizeParams($params),
            'request_headers' => $this->repository->sanitizeHeaders($headers),
            'severity' => $severity,
            'context' => [
                'app_slug' => $request->header('X-App'),
                'tenant_slug' => $request->header('X-Tenant'),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        ]);

        return $this->toData($log);
    }

    /**
     * @return Collection<int, TenantErrorLogData>
     */
    public function listForTenant(int $tenantId, ?string $severity = null, bool $unresolvedOnly = false): Collection
    {
        return $this->repository
            ->listForTenant($tenantId, $severity, $unresolvedOnly)
            ->map(fn (TenantErrorLogRepositoryData $dto) => $this->toData($dto));
    }

    /**
     * Get a specific error log entry by its error code.
     *
     * @param  string  $errorCode  code of the error to retrieve
     */
    public function getByCode(string $errorCode): ?TenantErrorLogData
    {
        $dto = $this->repository->findByCode($errorCode);

        return $dto ? $this->toData($dto) : null;
    }

    /**
     * Get a specific error log entry by its ID.
     *
     * @param  int  $id  ID of the error log entry to retrieve
     */
    public function getById(int $id): TenantErrorLogData
    {
        return $this->toData($this->repository->findById($id));
    }

    /**
     * Resolve a specific error log entry.
     *
     * @param  int  $id  ID of the error log entry to resolve
     */
    public function resolve(int $id): void
    {
        $this->repository->resolve($id);
    }

    /**
     * Unresolve a specific error log entry.
     *
     * @param  int  $id  ID of the error log entry to unresolve
     */
    public function unresolve(int $id): void
    {
        $this->repository->unresolve($id);
    }

    /**
     * Delete a specific error log entry.
     *
     * @param  int  $id  ID of the error log entry to delete
     */
    public function delete(int $id): void
    {
        $this->repository->delete($id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function formatTrace(Throwable $exception): array
    {
        return array_slice(
            array_map(fn (array $frame) => [
                'file' => $frame['file'] ?? null,
                'line' => $frame['line'] ?? null,
                'function' => isset($frame['class'])
                    ? "{$frame['class']}{$frame['type']}{$frame['function']}"
                    : $frame['function'],
            ], $exception->getTrace()),
            0,
            30,
        );
    }

    private function toData(TenantErrorLogRepositoryData $dto): TenantErrorLogData
    {
        return new TenantErrorLogData(
            id: $dto->id,
            tenantId: $dto->tenantId,
            userId: $dto->userId,
            errorCode: $dto->errorCode,
            exceptionClass: $dto->exceptionClass,
            message: $dto->message,
            file: $dto->file,
            line: $dto->line,
            trace: $dto->trace,
            requestUrl: $dto->requestUrl,
            requestMethod: $dto->requestMethod,
            requestParams: $dto->requestParams,
            requestHeaders: $dto->requestHeaders,
            severity: $dto->severity,
            context: $dto->context,
            resolved: $dto->resolvedAt !== null,
            resolvedAt: $dto->resolvedAt,
            createdAt: $dto->createdAt,
        );
    }
}
