<?php

namespace App\Data\Repositories\Central;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class DocumentRepositoryData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        public string $filePath,
        public string $fileName,
        public int $fileSize,
        public string $mimeType,
        #[MapInputName('uploaded_by_user_id')]
        public int $uploadedByUserId,
        #[MapInputName('uploaded_by_name')]
        public string $uploadedByName,
        public Carbon $createdAt,
        public Carbon $updatedAt,
    ) {}
}
