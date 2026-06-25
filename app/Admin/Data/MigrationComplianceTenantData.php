<?php

namespace App\Admin\Data;

use Spatie\LaravelData\Data;

class MigrationComplianceTenantData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public int $applied,
        public int $available,
    ) {}
}
