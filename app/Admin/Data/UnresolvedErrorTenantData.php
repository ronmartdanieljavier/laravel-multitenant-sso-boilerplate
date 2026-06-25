<?php

namespace App\Admin\Data;

use Spatie\LaravelData\Data;

class UnresolvedErrorTenantData extends Data
{
    public function __construct(
        public int $tenantId,
        public string $tenantName,
        public string $tenantSlug,
        public int $total,
        public int $error,
        public int $warning,
        public int $critical,
    ) {}
}
