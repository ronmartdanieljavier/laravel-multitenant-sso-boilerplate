<?php

namespace App\Auth\Data;

use App\Auth\Enums\Role;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class AppAccessData extends Data
{
    public function __construct(
        public readonly int $appId,
        public readonly string $name,
        public readonly string $slug,
        public readonly string $url,
        public readonly ?string $description,
        public readonly Role $role,
        /** @var DataCollection<int, TenantAccessData> */
        #[DataCollectionOf(TenantAccessData::class)]
        public readonly DataCollection $tenants,
    ) {}
}
