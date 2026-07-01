<?php

namespace App\Reports\Services;

use App\Models\Central\Report;
use App\Reports\Mail\ScheduledReportMail;
use App\Storage\StorageResolver;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class ReportDeliveryService
{
    public function __construct(
        private readonly StorageResolver $resolver,
    ) {}

    /**
     * @param  string[]  $recipients
     */
    public function sendToRecipients(Report $report, array $recipients): void
    {
        if (empty($recipients)) {
            return;
        }

        Mail::to($recipients)->queue(new ScheduledReportMail($report));
    }

    public function uploadToStorage(Report $report, ?string $basePath): void
    {
        if ($report->file_path === null) {
            return;
        }

        $disk = $report->tenant_id !== null
            ? $this->resolver->forTenant($report->tenant_id)
            : $this->resolver->forSystem();

        $filename = basename($report->file_path);
        $destination = rtrim($basePath ?? 'reports', '/').'/'.now()->format('Y/m/d').'/'.$filename;

        $contents = $disk->get($report->file_path);

        if ($contents === null) {
            throw new RuntimeException("Report file not found at {$report->file_path}.");
        }

        if ($disk->put($destination, $contents) === false) {
            throw new RuntimeException("Failed to upload report to storage at {$destination}.");
        }
    }
}
