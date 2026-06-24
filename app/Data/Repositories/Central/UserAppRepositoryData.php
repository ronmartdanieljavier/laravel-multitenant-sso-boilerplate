<?php

namespace App\Data\Repositories\Central;

use Spatie\LaravelData\Data;

class UserAppRepositoryData extends Data
{
    public function __construct(
        public int $appId,
        public string $appName,
        public string $role,
        /** @var int[] */
        public array $tenantIds,
    ) {}
}
