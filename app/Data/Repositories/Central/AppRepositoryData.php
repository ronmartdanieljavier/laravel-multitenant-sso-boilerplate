<?php

namespace App\Data\Repositories\Central;

use Spatie\LaravelData\Data;

class AppRepositoryData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $description,
        public bool $isActive,
    ) {}
}
