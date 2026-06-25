<?php

namespace App\Data\Repositories\Central;

use Spatie\LaravelData\Data;

class TenantRepositoryData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public bool $isActive,
        public bool $isMaintenance,
        public ?string $dbHost,
        public ?int $dbPort,
        public ?string $dbName,
        public ?string $dbUsername,
        public ?string $dbPassword,
        public bool $hasReadReplica,
        /** @var TenantMigrationVersionRepositoryData[] */
        public array $migrationVersions = [],
    ) {}
}
