<?php

namespace Tests\Feature\Reports;

use App\Models\Central\Report;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_download_a_successful_report(): void
    {
        Storage::fake();

        $user = User::factory()->create();
        Storage::put("reports/{$user->id}/report.pdf", 'fake-pdf-content');

        $report = Report::factory()->success()->create([
            'user_id' => $user->id,
            'file_path' => "reports/{$user->id}/report.pdf",
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/reports/{$report->id}/download");

        $response->assertOk();
    }

    public function test_user_cannot_download_a_pending_report(): void
    {
        $user = User::factory()->create();
        $report = Report::factory()->pending()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/api/v1/reports/{$report->id}/download");

        $response->assertUnprocessable();
    }

    public function test_user_cannot_download_a_failed_report(): void
    {
        $user = User::factory()->create();
        $report = Report::factory()->failed()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/api/v1/reports/{$report->id}/download");

        $response->assertUnprocessable();
    }

    public function test_user_cannot_download_another_users_report(): void
    {
        Storage::fake();

        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Storage::put("reports/{$otherUser->id}/report.pdf", 'fake-pdf-content');

        $report = Report::factory()->success()->create([
            'user_id' => $otherUser->id,
            'file_path' => "reports/{$otherUser->id}/report.pdf",
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/reports/{$report->id}/download");

        $response->assertForbidden();
    }
}
