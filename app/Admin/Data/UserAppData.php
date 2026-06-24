<?php

namespace App\Admin\Data;

use Spatie\LaravelData\Data;

class UserAppData extends Data
{
    /**
     * @param  array<int, int>  $tenantIds
     */
    public function __construct(
        public int $appId,
        public string $appName,
        public string $role,
        public array $tenantIds,
    ) {}
}
