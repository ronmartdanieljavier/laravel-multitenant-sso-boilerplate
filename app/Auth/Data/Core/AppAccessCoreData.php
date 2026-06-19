<?php

namespace App\Auth\Data\Core;

use App\Auth\Enums\Role;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class AppAccessCoreData extends Data
{
    public function __construct(
        public readonly int $appId,
        public readonly string $name,
        public readonly string $slug,
        public readonly string $url,
        public readonly Role $role,
        /** @var DataCollection<int, ClientAccessCoreData> */
        #[DataCollectionOf(ClientAccessCoreData::class)]
        public readonly DataCollection $clients,
    ) {}
}
