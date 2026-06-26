<?php

namespace App\Reports\Services;

use App\Models\Central\Report;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class ReportFileService
{
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

        if (Storage::disk('reports')->put($path, $content) === false) {
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
        $zipPath = Storage::disk('reports')->path("{$prefix}/{$zipName}");

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create ZIP archive at {$zipPath}");
        }

        foreach ($storagePaths as $storagePath) {
            $absolutePath = Storage::disk('reports')->path($storagePath);
            $zip->addFile($absolutePath, basename($storagePath));
        }

        $zip->close();

        return "{$prefix}/{$zipName}";
    }

    public function absolutePath(string $storagePath): string
    {
        return Storage::disk('reports')->path($storagePath);
    }

    public function exists(string $storagePath): bool
    {
        return Storage::disk('reports')->exists($storagePath);
    }
}
