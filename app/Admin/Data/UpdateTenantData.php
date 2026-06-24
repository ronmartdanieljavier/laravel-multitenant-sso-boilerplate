<?php

namespace App\Admin\Data;

use Spatie\LaravelData\Data;

class UpdateTenantData extends Data
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $dbHost,
        public ?int $dbPort,
        public ?string $dbName,
        public ?string $dbUsername,
        public ?string $dbPassword,
        public ?string $readReplicaHost,
        public ?int $readReplicaPort,
        public ?string $readReplicaUsername,
        public ?string $readReplicaPassword,
    ) {}
}
