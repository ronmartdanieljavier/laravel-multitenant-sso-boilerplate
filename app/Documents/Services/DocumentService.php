<?php

namespace App\Documents\Services;

use App\Data\Repositories\Central\DocumentRepositoryData;
use App\Documents\Data\DocumentData;
use App\Documents\Enums\DocumentSource;
use App\Models\Central\Report;
use App\Repositories\Central\DocumentRepository;
use App\Storage\StorageResolver;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class DocumentService
{
    public function __construct(
        protected DocumentRepository $documentRepository,
        protected StorageResolver $resolver,
    ) {}

    /**
     * @return LengthAwarePaginator<DocumentData>
     */
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return $this->documentRepository->paginate($perPage)
            ->through(fn (DocumentRepositoryData $d) => $this->toData($d));
    }

    /**
     * @return Collection<int, DocumentData>
     */
    public function list(): Collection
    {
        return $this->documentRepository->list()->map(fn (DocumentRepositoryData $d) => $this->toData($d));
    }

    public function find(int $id): DocumentData
    {
        return $this->toData($this->documentRepository->find($id));
    }

    public function getFilePath(int $id): string
    {
        return $this->documentRepository->find($id)->filePath;
    }

    public function store(
        string $title,
        ?string $description,
        UploadedFile $file,
        int $userId,
        string $userName,
        string $tenantSlug,
        int $tenantId,
    ): DocumentData {
        $disk = $this->resolver->forTenant($tenantId);
        $path = $disk->putFileAs("{$tenantSlug}/documents", $file, $file->hashName());

        $dto = $this->documentRepository->create([
            'title' => $title,
            'description' => $description,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType() ?? $file->getClientMimeType(),
            'uploaded_by_user_id' => $userId,
            'uploaded_by_name' => $userName,
            'source' => DocumentSource::Upload->value,
        ]);

        return $this->toData($dto);
    }

    public function createFromReport(Report $report): DocumentData
    {
        $report->loadMissing('user');

        $extension = pathinfo((string) $report->file_path, PATHINFO_EXTENSION);
        $mime = $extension === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        $fileName = basename((string) $report->file_path);
        $fileSize = $this->diskForReport($report)->size((string) $report->file_path);
        $title = ucwords(str_replace('_', ' ', $report->type)).' Report';

        $dto = $this->documentRepository->create([
            'title' => $title,
            'description' => null,
            'file_path' => $report->file_path,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'mime_type' => $mime,
            'uploaded_by_user_id' => $report->user_id,
            'uploaded_by_name' => $report->user->name ?? 'System',
            'source' => DocumentSource::Report->value,
        ]);

        return $this->toData($dto);
    }

    public function delete(int $id, int $tenantId): void
    {
        $dto = $this->documentRepository->find($id);
        $disk = $this->resolver->forTenant($tenantId);
        $disk->delete($dto->filePath);
        $this->documentRepository->delete($id);
    }

    /**
     * Return a download response for the given document.
     *
     * For cloud disks (S3/R2) this redirects to a short-lived signed URL so the
     * file transfer happens directly between the client and the storage backend.
     * For local disks it streams the file through the app.
     */
    public function downloadResponse(int $id, int $tenantId): RedirectResponse|StreamedResponse
    {
        $dto = $this->documentRepository->find($id);

        if ($dto->source === DocumentSource::Report) {
            return $this->resolver->forTenant($tenantId)->download($dto->filePath, $dto->fileName);
        }

        $disk = $this->resolver->forTenant($tenantId);

        try {
            $url = $disk->temporaryUrl($dto->filePath, now()->addMinutes(5));

            return redirect($url);
        } catch (\RuntimeException) {
            return $disk->download($dto->filePath, $dto->fileName);
        }
    }

    /**
     * Build a ZIP archive from the given document IDs and stream it to the client.
     *
     * @param  array<int>  $ids
     */
    public function downloadZipResponse(array $ids, int $tenantId): StreamedResponse
    {
        $documents = $this->documentRepository->findMany($ids);

        $tmpPath = tempnam(sys_get_temp_dir(), 'docs_zip_');

        $zip = new ZipArchive;
        $zip->open($tmpPath, ZipArchive::OVERWRITE);

        foreach ($documents as $doc) {
            $disk = $this->resolver->forTenant($tenantId);

            if (! $disk->exists($doc->filePath)) {
                continue;
            }

            $zip->addFromString($doc->fileName, $disk->get($doc->filePath));
        }

        $zip->close();

        return response()->streamDownload(function () use ($tmpPath): void {
            $stream = fopen($tmpPath, 'rb');
            fpassthru($stream);
            fclose($stream);
            @unlink($tmpPath);
        }, 'documents.zip', ['Content-Type' => 'application/zip']);
    }

    private function diskForReport(Report $report): Filesystem
    {
        return $report->tenant_id !== null
            ? $this->resolver->forTenant($report->tenant_id)
            : $this->resolver->forSystem();
    }

    private function toData(DocumentRepositoryData $d): DocumentData
    {
        return new DocumentData(
            id: $d->id,
            title: $d->title,
            description: $d->description,
            fileName: $d->fileName,
            fileSize: $d->fileSize,
            mimeType: $d->mimeType,
            uploadedByName: $d->uploadedByName,
            source: $d->source,
            createdAt: $d->createdAt,
        );
    }
}
