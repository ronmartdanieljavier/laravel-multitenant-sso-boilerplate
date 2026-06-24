<?php

namespace App\Admin\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class UserAppPermissionData extends Data
{
    /**
     * @param  array<int, int>  $tenantIds
     */
    public function __construct(
        #[MapInputName('app_id')]
        public int $appId,
        public string $role,
        #[MapInputName('tenant_ids')]
        public array $tenantIds = [],
    ) {}
}
