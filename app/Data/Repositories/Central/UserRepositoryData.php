<?php

namespace App\Data\Repositories\Central;

use Carbon\Carbon;
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
        public bool $isActive = true,
        public ?string $invitationToken = null,
        public ?Carbon $invitationSentAt = null,
    ) {}
}
