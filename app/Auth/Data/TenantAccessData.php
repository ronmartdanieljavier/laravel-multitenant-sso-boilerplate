<?php

namespace App\Auth\Data;

use App\Auth\Enums\Role;
use Spatie\LaravelData\Data;

class TenantAccessData extends Data
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $name,
        public readonly string $slug,
        public readonly Role $role,
        public readonly bool $isDefault,
    ) {}
}
