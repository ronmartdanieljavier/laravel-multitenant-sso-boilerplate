<?php

namespace App\Auth\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class AuthTokenData extends Data
{
    public function __construct(
        public readonly string $token,
        public readonly string $tokenType,
        public readonly UserData $user,
        /** @var DataCollection<int, AppAccessData> */
        #[DataCollectionOf(AppAccessData::class)]
        public readonly DataCollection $apps,
    ) {}
}
