<?php

namespace App\Admin\Data;

use Spatie\LaravelData\Data;

class MissingSystemSettingsData extends Data
{
    public function __construct(
        /** @var list<string> */
        public array $labels,
    ) {}
}
