<?php

namespace Tests\Feature\Reports;

use App\Models\Central\Report;
use App\Models\Central\User;
use App\Reports\Enums\ReportStatus;
use App\Reports\Generators\ReportGeneratorFactory;
use App\Reports\Jobs\GenerateReportJob;
use App\Reports\Services\ReportFileService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateReportJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_sets_status_to_processing_then_success(): void
    {
        $user = User::factory()->create();
        $report = Report::factory()->screen()->create([
            'user_id' => $user->id,
            'status' => ReportStatus::Pending->value,
        ]);

        $job = new GenerateReportJob($report);
        $job->handle(new ReportGeneratorFactory($this->app->make(ReportFileService::class)));

        $report->refresh();

        $this->assertSame(ReportStatus::Success, $report->status);
        $this->assertNotNull($report->completed_at);
        $this->assertNotNull($report->started_at);
    }

    public function test_job_sets_status_to_failed_on_error(): void
    {
        $user = User::factory()->create();
        $report = Report::factory()->pdf()->create([
            'user_id' => $user->id,
            'status' => ReportStatus::Pending->value,
        ]);

        $job = new GenerateReportJob($report);
        $job->failed(new Exception('Test failure message'));

        $report->refresh();

        $this->assertSame(ReportStatus::Failed, $report->status);
        $this->assertSame('Test failure message', $report->error_message);
        $this->assertNotNull($report->completed_at);
    }

    public function test_screen_report_job_completes_successfully(): void
    {
        $user = User::factory()->create();
        $report = Report::factory()->screen()->create([
            'user_id' => $user->id,
            'type' => 'user_activity',
            'parameters' => ['from' => '2026-01-01', 'to' => '2026-06-20'],
        ]);

        $job = new GenerateReportJob($report);
        $job->handle(new ReportGeneratorFactory($this->app->make(ReportFileService::class)));

        $report->refresh();
        $this->assertSame(ReportStatus::Success, $report->status);
    }
}
