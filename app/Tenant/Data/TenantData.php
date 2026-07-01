<?php

namespace App\Tenant\Data;

use Spatie\LaravelData\Data;

class TenantData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly bool $isCurrent,
        public readonly bool $isMaintenance,
    ) {}
}
