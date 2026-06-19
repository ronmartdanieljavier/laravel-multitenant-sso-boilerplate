<?php

namespace App\Auth\Data\Core;

use App\Auth\Enums\Role;
use Spatie\LaravelData\Data;

class TenantAccessCoreData extends Data
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $name,
        public readonly string $slug,
        public readonly Role $role,
        public readonly bool $isDefault,
    ) {}
}
