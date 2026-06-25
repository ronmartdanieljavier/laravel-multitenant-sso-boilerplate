<?php

namespace App\Admin\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class UserData extends Data
{
    /**
     * @param  array<int, UserAppData>  $apps
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public bool $isActive,
        public ?Carbon $invitationSentAt,
        public ?string $profilePictureUrl,
        public ?Carbon $createdAt,
        public array $apps,
    ) {}
}
