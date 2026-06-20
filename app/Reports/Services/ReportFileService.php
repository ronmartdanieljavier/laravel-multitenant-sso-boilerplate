<?php

namespace App\Reports\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class ReportFileService
{
    public function storeFile(string $content, string $filename, int $userId): string
    {
        $path = "reports/{$userId}/{$filename}";

        if (Storage::put($path, $content) === false) {
            throw new RuntimeException("Failed to write report file at {$path}.");
        }

        return $path;
    }

    /**
     * @param  string[]  $paths  Absolute paths to PDF files on disk
     */
    public function mergePdfs(array $paths): string
    {
        // TODO: install setasign/fpdi or spatie/pdf-to-image and implement PDF merging.
        throw new RuntimeException('PDF merging is not yet implemented. Install a PDF merge package first.');
    }

    /**
     * @param  string[]  $storagePaths  Storage-relative paths to include in the ZIP
     */
    public function zipFiles(array $storagePaths, string $zipName): string
    {
        $zip = new ZipArchive;
        $zipPath = storage_path("app/reports/{$zipName}");

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create ZIP archive at {$zipPath}");
        }

        foreach ($storagePaths as $storagePath) {
            $absolutePath = Storage::path($storagePath);
            $zip->addFile($absolutePath, basename($storagePath));
        }

        $zip->close();

        return "reports/{$zipName}";
    }

    public function absolutePath(string $storagePath): string
    {
        return Storage::path($storagePath);
    }

    public function exists(string $storagePath): bool
    {
        return Storage::exists($storagePath);
    }
}
