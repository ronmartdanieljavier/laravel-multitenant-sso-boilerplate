<?php

namespace Tests\Feature\Reports;

use App\Admin\Services\TenantSettingsService;
use App\Models\Central\Report;
use App\Models\Central\User;
use App\Reports\Generators\PdfReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PdfReportGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_generator_writes_file_to_reports_disk(): void
    {
        Storage::fake('reports');

        $user = User::factory()->create();
        $report = Report::factory()->pdf()->create(['user_id' => $user->id]);

        $generator = new PdfReportGenerator($report, app(TenantSettingsService::class));
        $result = $generator->generate();

        Storage::disk('reports')->assertExists($result->filePath);
    }

    public function test_pdf_generator_returns_correct_storage_path(): void
    {
        Storage::fake('reports');

        $user = User::factory()->create();
        $report = Report::factory()->pdf()->create(['user_id' => $user->id]);

        $generator = new PdfReportGenerator($report, app(TenantSettingsService::class));
        $result = $generator->generate();

        $this->assertStringContainsString("report_{$report->id}.pdf", $result->filePath);
    }
}
