<?php

namespace App\Admin\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class UpdateUserData extends Data
{
    /**
     * @param  DataCollection<int, UserAppPermissionData>  $apps
     */
    public function __construct(
        public string $name,
        public string $email,
        #[DataCollectionOf(UserAppPermissionData::class)]
        public DataCollection $apps,
    ) {}
}
