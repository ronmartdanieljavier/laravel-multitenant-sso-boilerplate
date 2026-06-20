<?php

namespace Tests\Feature\Reports;

use App\Models\Central\Report;
use App\Models\Central\User;
use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportStatus;
use App\Reports\Jobs\GenerateReportJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReportDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_request_a_report(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/reports', [
            'type' => 'user_activity',
            'format' => ReportFormat::Screen->value,
            'delivery' => ReportDelivery::None->value,
        ]);

        $response->assertCreated();
        $response->assertJsonFragment(['status' => ReportStatus::Pending->value]);

        $this->assertDatabaseHas('reports', [
            'user_id' => $user->id,
            'type' => 'user_activity',
            'status' => ReportStatus::Pending->value,
        ]);

        Queue::assertPushed(GenerateReportJob::class);
    }

    public function test_unauthenticated_user_cannot_request_a_report(): void
    {
        $response = $this->postJson('/api/reports', [
            'type' => 'user_activity',
            'format' => ReportFormat::Screen->value,
            'delivery' => ReportDelivery::None->value,
        ]);

        $response->assertUnauthorized();
    }

    public function test_report_request_validates_format(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/reports', [
            'type' => 'user_activity',
            'format' => 'invalid_format',
            'delivery' => ReportDelivery::None->value,
        ]);

        $response->assertUnprocessable();
        Queue::assertNothingPushed();
    }

    public function test_user_can_list_their_own_reports(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Report::factory()->count(3)->create(['user_id' => $user->id]);
        Report::factory()->count(2)->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->getJson('/api/reports');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_user_can_view_their_own_report(): void
    {
        $user = User::factory()->create();
        $report = Report::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/api/reports/{$report->id}");

        $response->assertOk();
        $response->assertJsonFragment(['id' => $report->id]);
    }

    public function test_user_cannot_view_another_users_report(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $report = Report::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->getJson("/api/reports/{$report->id}");

        $response->assertForbidden();
    }

    public function test_user_can_delete_their_own_report(): void
    {
        $user = User::factory()->create();
        $report = Report::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->deleteJson("/api/reports/{$report->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('reports', ['id' => $report->id]);
    }
}
