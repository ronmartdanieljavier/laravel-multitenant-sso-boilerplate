<?php

namespace App\Admin\Data;

use Spatie\LaravelData\Data;

class UpdateAppData extends Data
{
    public function __construct(
        public string $name,
        public ?string $description,
    ) {}
}
