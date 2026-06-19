<?php

namespace App\Login\Data\Core;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class AuthTokenCoreData extends Data
{
    public function __construct(
        public readonly string $token,
        public readonly string $tokenType,
        public readonly UserCoreData $user,
        /** @var DataCollection<int, AppAccessCoreData> */
        #[DataCollectionOf(AppAccessCoreData::class)]
        public readonly DataCollection $apps,
    ) {}
}
