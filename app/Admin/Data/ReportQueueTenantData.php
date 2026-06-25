<?php

namespace App\Admin\Data;

use Spatie\LaravelData\Data;

class ReportQueueTenantData extends Data
{
    public function __construct(
        public int $tenantId,
        public string $tenantName,
        public string $tenantSlug,
        public int $pending,
        public int $processing,
        public int $failed,
    ) {}
}
