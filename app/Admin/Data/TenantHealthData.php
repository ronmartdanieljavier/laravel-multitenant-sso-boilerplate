<?php

namespace App\Admin\Data;

use Spatie\LaravelData\Data;

class TenantHealthData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public bool $isActive,
        public bool $isMaintenance,
        public bool $hasReadReplica,
        public int $userCount,
        public int $migrationCount,
        public ?string $lastMigration,
        public int $pendingReports,
        public int $failedReports,
        public ?string $lastReportAt,
        public string $healthStatus,
    ) {}
}
