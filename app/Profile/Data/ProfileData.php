<?php

namespace App\Profile\Data;

use Spatie\LaravelData\Data;

class ProfileData extends Data
{
    public function __construct(
        public ?int $id,
        public ?string $name,
        public ?string $email,
        public ?string $profilePicture,
    ) {}
}
