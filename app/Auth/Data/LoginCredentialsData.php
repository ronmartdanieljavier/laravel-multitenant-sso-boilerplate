<?php

namespace App\Auth\Data;

use Spatie\LaravelData\Data;

class LoginCredentialsData extends Data
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
    ) {}
}
