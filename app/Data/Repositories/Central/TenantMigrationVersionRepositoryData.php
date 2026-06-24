<?php

namespace App\Data\Repositories\Central;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class TenantMigrationVersionRepositoryData extends Data
{
    public function __construct(
        public string $migration,
        public int $batch,
        public ?Carbon $migratedAt,
    ) {}
}
