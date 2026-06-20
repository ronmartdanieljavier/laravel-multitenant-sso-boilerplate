<?php

namespace App\Reports\Data;

use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use Spatie\LaravelData\Data;

class ReportRequestData extends Data
{
    public function __construct(
        public readonly string $type,
        public readonly ReportFormat $format,
        public readonly ReportDelivery $delivery,
        /** @var array<string, mixed>|null */
        public readonly ?array $parameters = null,
        public readonly ?string $batch_id = null,
    ) {}
}
