<?php

namespace App\Reports\Services;

use App\Models\Central\Report;
use App\Reports\Mail\ScheduledReportMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ReportDeliveryService
{
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

    public function uploadToS3(Report $report, ?string $basePath): void
    {
        if ($report->file_path === null) {
            return;
        }

        $filename = basename($report->file_path);
        $destination = rtrim($basePath ?? 'reports', '/').'/'.now()->format('Y/m/d').'/'.$filename;

        $contents = Storage::get($report->file_path);

        if ($contents === null) {
            throw new RuntimeException("Report file not found at {$report->file_path}.");
        }

        if (Storage::disk('s3')->put($destination, $contents) === false) {
            throw new RuntimeException("Failed to upload report to S3 at {$destination}.");
        }
    }
}
