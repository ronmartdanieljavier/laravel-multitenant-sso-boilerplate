<?php

namespace App\Reports\Data;

use Spatie\LaravelData\Data;

class ReportResultData extends Data
{
    public function __construct(
        /** @var array<string, mixed>|null */
        public readonly ?array $data = null,
        public readonly ?string $filePath = null,
    ) {}
}
