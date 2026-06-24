<?php

namespace App\Reports\Jobs;

use App\Models\Central\Report;
use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportStatus;
use App\Reports\Generators\ReportGeneratorFactory;
use App\Reports\Mail\ReportReadyMail;
use App\Reports\Services\ReportDeliveryService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Throwable;

class GenerateReportJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public int $backoff = 30;

    public function __construct(public readonly string $reportId)
    {
        $this->onQueue('reports');
    }

    public function handle(ReportGeneratorFactory $factory): void
    {
        $report = Report::findOrFail($this->reportId);

        if ($this->batch()?->cancelled()) {
            $report->update([
                'status' => ReportStatus::Failed,
                'error_message' => 'Batch was cancelled.',
                'completed_at' => now(),
            ]);

            return;
        }

        $report->update([
            'status' => ReportStatus::Processing,
            'started_at' => now(),
        ]);

        $generator = $factory->make($report);
        $result = $generator->generate();

        $report->update([
            'status' => ReportStatus::Success,
            'file_path' => $result->filePath,
            'completed_at' => now(),
        ]);

        $this->deliver($report, app(ReportDeliveryService::class));
    }

    private function deliver(Report $report, ReportDeliveryService $deliveryService): void
    {
        $parameters = $report->parameters ?? [];
        $isSubscriptionReport = isset($parameters['subscription_id']);

        match ($report->delivery) {
            ReportDelivery::Email => $isSubscriptionReport
                ? $deliveryService->sendToRecipients($report, $parameters['recipients'] ?? [])
                : $this->sendToReportUser($report),
            ReportDelivery::S3 => $deliveryService->uploadToS3($report, $parameters['s3_path'] ?? null),
            ReportDelivery::EmailAndS3 => $this->handleEmailAndS3($report, $deliveryService, $parameters),
            default => null,
        };
    }

    private function sendToReportUser(Report $report): void
    {
        $report->loadMissing('user');
        Mail::to($report->user)->queue(new ReportReadyMail($report));
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function handleEmailAndS3(Report $report, ReportDeliveryService $deliveryService, array $parameters): void
    {
        $deliveryService->sendToRecipients($report, $parameters['recipients'] ?? []);
        $deliveryService->uploadToS3($report, $parameters['s3_path'] ?? null);
    }

    public function failed(Throwable $e): void
    {
        Report::find($this->reportId)?->update([
            'status' => ReportStatus::Failed,
            'error_message' => $e->getMessage(),
            'completed_at' => now(),
        ]);
    }
}
