<?php

namespace App\Admin\Data;

use Spatie\LaravelData\Data;

class UnresolvedErrorSummaryData extends Data
{
    /**
     * @param  array<int, UnresolvedErrorTenantData>  $byTenant
     */
    public function __construct(
        public int $total,
        public int $error,
        public int $warning,
        public int $critical,
        public array $byTenant,
    ) {}
}
