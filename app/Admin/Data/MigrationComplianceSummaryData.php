<?php

namespace App\Admin\Data;

use Spatie\LaravelData\Data;

class MigrationComplianceSummaryData extends Data
{
    /**
     * @param  array<int, MigrationComplianceTenantData>  $behind
     */
    public function __construct(
        public int $total,
        public int $upToDate,
        public int $behindCount,
        public int $availableMigrations,
        public array $behind,
    ) {}
}
