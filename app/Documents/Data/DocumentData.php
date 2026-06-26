<?php

namespace App\Documents\Data;

use App\Documents\Enums\DocumentSource;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class DocumentData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        public string $fileName,
        public int $fileSize,
        public string $mimeType,
        public string $uploadedByName,
        public DocumentSource $source,
        public Carbon $createdAt,
    ) {}
}
