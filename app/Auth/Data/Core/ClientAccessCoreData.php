<?php

namespace App\Auth\Data\Core;

use App\Auth\Enums\Role;
use Spatie\LaravelData\Data;

class ClientAccessCoreData extends Data
{
    public function __construct(
        public readonly int $clientId,
        public readonly string $name,
        public readonly string $slug,
        public readonly Role $role,
        public readonly bool $isDefault,
    ) {}
}
