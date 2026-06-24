<?php

namespace App\Admin\Data;

use Spatie\LaravelData\Data;

class TenantData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public bool $isActive,
        public ?string $dbHost,
        public ?int $dbPort,
        public ?string $dbName,
        public ?string $dbUsername,
        public bool $isPasswordSet,
        public bool $hasReadReplica,
        public int $migrationCount,
        public int $userCount,
        public int $pendingReports,
        public int $failedReports,
        public string $healthStatus,
    ) {}
}
