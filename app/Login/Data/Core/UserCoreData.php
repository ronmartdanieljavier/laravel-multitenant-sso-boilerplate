<?php

namespace App\Login\Data\Core;

use Spatie\LaravelData\Data;

class UserCoreData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
    ) {}
}
