<?php

namespace App\TenantJobs\Data;

use App\TenantJobs\Enums\TenantJobStatus;
use Carbon\Carbon;
use Spatie\LaravelData\Data;

class TenantJobData extends Data
{
    public function __construct(
        public string $id,
        public string $jobClass,
        public string $displayName,
        public TenantJobStatus $status,
        public ?string $errorMessage,
        public ?Carbon $startedAt,
        public ?Carbon $finishedAt,
        public ?Carbon $createdAt,
        public ?int $durationSeconds,
    ) {}
}
