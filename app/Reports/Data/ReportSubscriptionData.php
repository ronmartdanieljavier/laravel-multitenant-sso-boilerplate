<?php

namespace App\Reports\Data;

use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportFrequency;
use Carbon\Carbon;
use Spatie\LaravelData\Data;

class ReportSubscriptionData extends Data
{
    public function __construct(
        public int $id,
        public string $type,
        public ReportFormat $format,
        public ReportFrequency $frequency,
        public ReportDelivery $delivery,
        public ?array $recipients,
        public ?string $s3Path,
        public bool $isActive,
        public ?Carbon $lastDispatchedAt,
        public Carbon $createdAt,
    ) {}
}
