<?php

namespace App\Repositories\Central;

use App\Data\Repositories\Central\TenantErrorLogRepositoryData;
use App\Models\Central\TenantErrorLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantErrorLogRepository
{
    private const SENSITIVE_PARAM_KEYS = [
        'password', 'password_confirmation', 'token', 'secret', 'key',
        'api_key', 'access_token', 'refresh_token', 'credit_card', 'cvv',
    ];

    private const SENSITIVE_HEADER_KEYS = [
        'authorization', 'cookie', 'x-api-key', 'x-auth-token',
    ];

    public function __construct(
        protected TenantErrorLog $model
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): TenantErrorLogRepositoryData
    {
        $log = $this->model->create($data);

        return $this->toData($log);
    }

    /**
     * @return Collection<int, TenantErrorLogRepositoryData>
     */
    public function listForTenant(int $tenantId, ?string $severity = null, bool $unresolvedOnly = false): Collection
    {
        $query = $this->model
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at');

        if ($severity !== null) {
            $query->where('severity', $severity);
        }

        if ($unresolvedOnly) {
            $query->whereNull('resolved_at');
        }

        return $query->get()->map(fn (TenantErrorLog $log) => $this->toData($log));
    }

    /**
     * Count unresolved logs grouped by severity across all tenants.
     *
     * @return array<string, int> keys: error, warning, critical
     */
    public function countUnresolvedBySeverity(): array
    {
        return $this->model
            ->whereNull('resolved_at')
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Count unresolved logs per tenant, broken down by severity.
     * Each row is a stdClass with: tenant_id, tenant_name, tenant_slug, severity, count.
     *
     * @return Collection<int, \stdClass>
     */
    public function countUnresolvedByTenant(): Collection
    {
        return DB::table('tenant_error_logs')
            ->join('tenants', 'tenant_error_logs.tenant_id', '=', 'tenants.id')
            ->whereNull('tenant_error_logs.resolved_at')
            ->selectRaw('tenant_error_logs.tenant_id, tenants.name as tenant_name, tenants.slug as tenant_slug, tenant_error_logs.severity, COUNT(*) as count')
            ->groupBy('tenant_error_logs.tenant_id', 'tenants.name', 'tenants.slug', 'tenant_error_logs.severity')
            ->get();
    }

    public function findByCode(string $errorCode): ?TenantErrorLogRepositoryData
    {
        $log = $this->model->where('error_code', $errorCode)->first();

        return $log ? $this->toData($log) : null;
    }

    public function findById(int $id): TenantErrorLogRepositoryData
    {
        return $this->toData($this->model->findOrFail($id));
    }

    public function resolve(int $id): void
    {
        $this->model->findOrFail($id)->update(['resolved_at' => now()]);
    }

    public function unresolve(int $id): void
    {
        $this->model->findOrFail($id)->update(['resolved_at' => null]);
    }

    public function delete(int $id): void
    {
        $this->model->findOrFail($id)->delete();
    }

    /**
     * Generate a unique error code for a tenant.
     * Format: E-{SLUG}-{8 hex chars}
     */
    public function generateErrorCode(string $tenantSlug): string
    {
        $slug = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $tenantSlug));
        $slug = substr($slug, 0, 6);

        do {
            $code = 'E-'.$slug.'-'.strtoupper(Str::random(8));
        } while ($this->model->where('error_code', $code)->exists());

        return $code;
    }

    /**
     * Sanitize request parameters — redact sensitive keys.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function sanitizeParams(array $params): array
    {
        return $this->redactKeys($params, self::SENSITIVE_PARAM_KEYS);
    }

    /**
     * Sanitize request headers — redact sensitive keys.
     *
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    public function sanitizeHeaders(array $headers): array
    {
        return $this->redactKeys($headers, self::SENSITIVE_HEADER_KEYS);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  string[]  $sensitiveKeys
     * @return array<string, mixed>
     */
    private function redactKeys(array $data, array $sensitiveKeys): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $lower = strtolower((string) $key);
            $isSensitive = in_array($lower, $sensitiveKeys, strict: true)
                || str_contains($lower, 'password')
                || str_contains($lower, 'secret')
                || str_contains($lower, 'token');

            $result[$key] = $isSensitive ? '[REDACTED]' : $value;
        }

        return $result;
    }

    private function toData(TenantErrorLog $log): TenantErrorLogRepositoryData
    {
        return new TenantErrorLogRepositoryData(
            id: $log->id,
            tenantId: $log->tenant_id,
            userId: $log->user_id,
            errorCode: $log->error_code,
            exceptionClass: $log->exception_class,
            message: $log->message,
            file: $log->file,
            line: $log->line,
            trace: $log->trace,
            requestUrl: $log->request_url,
            requestMethod: $log->request_method,
            requestParams: $log->request_params,
            requestHeaders: $log->request_headers,
            severity: $log->severity,
            context: $log->context,
            resolvedAt: $log->resolved_at,
            createdAt: $log->created_at,
        );
    }
}
