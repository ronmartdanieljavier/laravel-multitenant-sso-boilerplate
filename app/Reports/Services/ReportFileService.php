<?php

namespace App\Reports\Services;

use App\Models\Central\Report;
use App\Storage\StorageResolver;
use Illuminate\Contracts\Filesystem\Filesystem;
use RuntimeException;
use ZipArchive;

class ReportFileService
{
    public function __construct(
        private readonly StorageResolver $resolver,
    ) {}

    private function diskFor(Report $report): Filesystem
    {
        return $report->tenant_id !== null
            ? $this->resolver->forTenant($report->tenant_id)
            : $this->resolver->forSystem();
    }

    /**
     * Compute the storage directory for a report's generated file.
     * Tenant reports live under {slug}/reports; user-only reports fall back to reports/{userId}.
     */
    public static function prefix(Report $report): string
    {
        $report->loadMissing('tenant');

        return $report->tenant
            ? "{$report->tenant->slug}/reports"
            : "reports/{$report->user_id}";
    }

    public function storeFile(string $content, string $filename, Report $report): string
    {
        $path = self::prefix($report).'/'.$filename;

        if ($this->diskFor($report)->put($path, $content) === false) {
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
    public function zipFiles(array $storagePaths, string $zipName, string $prefix = 'reports'): string
    {
        $zip = new ZipArchive;
        $disk = $this->resolver->forSystem();
        $zipPath = $disk->path("{$prefix}/{$zipName}");

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create ZIP archive at {$zipPath}");
        }

        foreach ($storagePaths as $storagePath) {
            $absolutePath = $disk->path($storagePath);
            $zip->addFile($absolutePath, basename($storagePath));
        }

        $zip->close();

        return "{$prefix}/{$zipName}";
    }

    public function absolutePath(string $storagePath, Report $report): string
    {
        return $this->diskFor($report)->path($storagePath);
    }

    public function exists(string $storagePath, Report $report): bool
    {
        return $this->diskFor($report)->exists($storagePath);
    }
}
