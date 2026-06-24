<?php

namespace App\Data\Repositories\Central;

use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportStatus;
use Carbon\Carbon;
use Spatie\LaravelData\Data;

class ReportRepositoryData extends Data
{
    public function __construct(
        public string $id,
        public int $userId,
        public ?int $tenantId,
        public string $type,
        public ReportFormat $format,
        public ReportDelivery $delivery,
        public ReportStatus $status,
        public ?array $parameters,
        public ?string $filePath,
        public ?string $errorMessage,
        public ?string $batchId,
        public ?Carbon $startedAt,
        public ?Carbon $completedAt,
        public ?Carbon $createdAt,
    ) {}
}
