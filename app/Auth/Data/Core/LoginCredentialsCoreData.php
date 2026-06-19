<?php

namespace App\Auth\Data\Core;

use Spatie\LaravelData\Data;

class LoginCredentialsCoreData extends Data
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
    ) {}
}
