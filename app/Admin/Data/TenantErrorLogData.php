<?php

namespace App\Admin\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class TenantErrorLogData extends Data
{
    /**
     * @param  array<int, array<string, mixed>>|null  $trace
     * @param  array<string, mixed>|null  $requestParams
     * @param  array<string, mixed>|null  $requestHeaders
     * @param  array<string, mixed>|null  $context
     */
    public function __construct(
        public int $id,
        public int $tenantId,
        public ?int $userId,
        public string $errorCode,
        public string $exceptionClass,
        public string $message,
        public ?string $file,
        public ?int $line,
        public ?array $trace,
        public ?string $requestUrl,
        public ?string $requestMethod,
        public ?array $requestParams,
        public ?array $requestHeaders,
        public string $severity,
        public ?array $context,
        public bool $resolved,
        public ?Carbon $resolvedAt,
        public Carbon $createdAt,
    ) {}
}
