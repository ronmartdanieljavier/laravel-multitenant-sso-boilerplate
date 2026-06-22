<?php

namespace App\Data\Repositories\Central;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class UserRepositoryData extends Data
{
    public function __construct(
        public ?int $id,
        public ?string $name,
        public ?string $email,
        #[MapInputName('profile_picture')]
        public ?string $profilePicture,
    ) {}
}
