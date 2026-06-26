<?php

namespace App\Reports\Jobs;

use App\Documents\Services\DocumentService;
use App\Models\Central\Report;
use App\Models\Central\Tenant;
use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportStatus;
use App\Reports\Generators\ReportGeneratorFactory;
use App\Reports\Mail\ReportReadyMail;
use App\Reports\Services\ReportDeliveryService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class GenerateReportJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public int $backoff = 30;

    public function __construct(
        public readonly string $reportId,
        string $queue = 'reports',
        ?int $timeout = null,
        ?string $connection = null,
    ) {
        $this->onQueue($queue);

        if ($timeout !== null) {
            $this->timeout = $timeout;
        }

        if ($connection !== null) {
            $this->onConnection($connection);
        }
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

        $this->registerDocument($report);
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

    private function registerDocument(Report $report): void
    {
        if (! in_array($report->format, [ReportFormat::Pdf, ReportFormat::Excel], strict: true)) {
            return;
        }

        if ($report->tenant_id === null) {
            return;
        }

        $report->loadMissing('tenant');

        if (! $report->tenant instanceof Tenant) {
            return;
        }

        $this->configureTenantConnection($report->tenant);

        app(DocumentService::class)->createFromReport($report);
    }

    private function configureTenantConnection(Tenant $tenant): void
    {
        $driver = config('database.connections.tenant.driver', 'mysql');
        $defaultPort = $driver === 'pgsql' ? 5432 : 3306;

        if ($driver === 'pgsql') {
            $connection = [
                'driver' => 'pgsql',
                'host' => $tenant->db_host,
                'port' => $tenant->db_port ?? $defaultPort,
                'database' => $tenant->db_name,
                'username' => $tenant->db_username,
                'password' => $tenant->db_password,
                'charset' => 'utf8',
                'prefix' => '',
                'schema' => 'public',
                'sslmode' => 'prefer',
            ];
        } else {
            $connection = [
                'driver' => 'mysql',
                'host' => $tenant->db_host,
                'port' => $tenant->db_port ?? $defaultPort,
                'database' => $tenant->db_name,
                'username' => $tenant->db_username,
                'password' => $tenant->db_password,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
            ];
        }

        Config::set('database.connections.tenant', $connection);
        DB::purge('tenant');
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
