<?php

namespace App\Data\Repositories\Central;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class UserWithPermissionsRepositoryData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public bool $isActive,
        public ?Carbon $invitationSentAt,
        public ?string $profilePictureUrl,
        public ?Carbon $createdAt,
        /** @var UserAppRepositoryData[] */
        public array $apps,
    ) {}
}
