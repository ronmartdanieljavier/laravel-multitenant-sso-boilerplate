<?php

namespace App\Admin\Data;

use Spatie\LaravelData\Data;

class TenantHealthSummaryData extends Data
{
    public function __construct(
        public int $total,
        public int $healthy,
        public int $warning,
        public int $critical,
    ) {}
}
