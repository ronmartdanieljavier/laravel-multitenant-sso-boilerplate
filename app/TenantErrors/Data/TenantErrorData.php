<?php

namespace App\TenantErrors\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class TenantErrorData extends Data
{
    /**
     * @param  array<string, mixed>|null  $requestParams
     * @param  array<string, mixed>|null  $context
     */
    public function __construct(
        public int $id,
        public string $errorCode,
        public string $exceptionClass,
        public string $message,
        public string $severity,
        public bool $resolved,
        public ?Carbon $resolvedAt,
        public Carbon $createdAt,
        public ?string $requestUrl,
        public ?string $requestMethod,
        public ?array $requestParams,
        public ?array $context,
    ) {}
}
