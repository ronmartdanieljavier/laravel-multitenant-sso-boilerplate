<?php

namespace App\Reports\Jobs;

use App\Models\Central\Report;
use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportStatus;
use App\Reports\Generators\ReportGeneratorFactory;
use App\Reports\Mail\ReportReadyMail;
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

        if ($this->report->delivery === ReportDelivery::Email) {
            $this->report->loadMissing('user');
            Mail::to($this->report->user)->queue(new ReportReadyMail($this->report));
        }
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
