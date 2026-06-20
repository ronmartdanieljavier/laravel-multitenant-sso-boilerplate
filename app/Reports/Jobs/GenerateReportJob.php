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
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class GenerateReportJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public int $backoff = 30;

    public function __construct(public readonly Report $report)
    {
        $this->onQueue('reports');
    }

    public function handle(ReportGeneratorFactory $factory): void
    {
        if ($this->batch()?->cancelled()) {
            $this->report->update([
                'status' => ReportStatus::Failed,
                'error_message' => 'Batch was cancelled.',
                'completed_at' => now(),
            ]);

            return;
        }

        $this->report->update([
            'status' => ReportStatus::Processing,
            'started_at' => now(),
        ]);

        $generator = $factory->make($this->report);
        $result = $generator->generate();

        $this->report->update([
            'status' => ReportStatus::Success,
            'file_path' => $result->filePath,
            'completed_at' => now(),
        ]);

        $this->deliver(app(ReportDeliveryService::class));
    }

    private function deliver(ReportDeliveryService $deliveryService): void
    {
        $parameters = $this->report->parameters ?? [];
        $isSubscriptionReport = isset($parameters['subscription_id']);

        match ($this->report->delivery) {
            ReportDelivery::Email => $isSubscriptionReport
                ? $deliveryService->sendToRecipients($this->report, $parameters['recipients'] ?? [])
                : $this->sendToReportUser(),
            ReportDelivery::S3 => $deliveryService->uploadToS3($this->report, $parameters['s3_path'] ?? null),
            ReportDelivery::EmailAndS3 => $this->handleEmailAndS3($deliveryService, $parameters),
            default => null,
        };
    }

    private function sendToReportUser(): void
    {
        $this->report->loadMissing('user');
        Mail::to($this->report->user)->queue(new ReportReadyMail($this->report));
    }

    private function handleEmailAndS3(ReportDeliveryService $deliveryService, array $parameters): void
    {
        $deliveryService->sendToRecipients($this->report, $parameters['recipients'] ?? []);
        $deliveryService->uploadToS3($this->report, $parameters['s3_path'] ?? null);
    }

    public function failed(Throwable $e): void
    {
        $this->report->update([
            'status' => ReportStatus::Failed,
            'error_message' => $e->getMessage(),
            'completed_at' => now(),
        ]);
    }
}
