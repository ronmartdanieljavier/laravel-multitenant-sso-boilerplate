<?php

namespace App\Data\Repositories\Central;

use App\TenantJobs\Enums\TenantJobStatus;
use Carbon\Carbon;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class TenantJobRecordRepositoryData extends Data
{
    public function __construct(
        public string $id,
        #[MapInputName('tracking_id')]
        public string $trackingId,
        #[MapInputName('tenant_id')]
        public int $tenantId,
        #[MapInputName('user_id')]
        public ?int $userId,
        #[MapInputName('job_class')]
        public string $jobClass,
        #[MapInputName('display_name')]
        public string $displayName,
        public TenantJobStatus $status,
        #[MapInputName('error_message')]
        public ?string $errorMessage,
        #[MapInputName('started_at')]
        public ?Carbon $startedAt,
        #[MapInputName('finished_at')]
        public ?Carbon $finishedAt,
        #[MapInputName('created_at')]
        public ?Carbon $createdAt,
    ) {}
}
