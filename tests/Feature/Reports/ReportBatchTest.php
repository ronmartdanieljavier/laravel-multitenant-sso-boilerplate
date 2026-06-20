<?php

namespace Tests\Feature\Reports;

use App\Models\Central\Report;
use App\Models\Central\User;
use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ReportBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_dispatch_creates_multiple_reports(): void
    {
        Bus::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/reports/batch', [
            'reports' => [
                ['type' => 'user_activity', 'format' => ReportFormat::Screen->value, 'delivery' => ReportDelivery::None->value],
                ['type' => 'app_access', 'format' => ReportFormat::Screen->value, 'delivery' => ReportDelivery::None->value],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonFragment(['report_count' => 2]);

        $this->assertDatabaseCount('reports', 2);

        $this->assertDatabaseHas('reports', [
            'user_id' => $user->id,
            'type' => 'user_activity',
            'status' => ReportStatus::Pending->value,
        ]);
    }

    public function test_all_batch_reports_share_the_same_batch_id(): void
    {
        Bus::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/reports/batch', [
            'reports' => [
                ['type' => 'user_activity', 'format' => ReportFormat::Screen->value, 'delivery' => ReportDelivery::None->value],
                ['type' => 'app_access', 'format' => ReportFormat::Screen->value, 'delivery' => ReportDelivery::None->value],
            ],
        ]);

        $batchId = $response->json('batch_id');

        $this->assertNotNull($batchId);
        $this->assertDatabaseCount('reports', 2);

        Report::where('user_id', $user->id)->each(
            fn ($r) => $this->assertSame($batchId, $r->batch_id)
        );
    }

    public function test_batch_rejects_mixed_formats(): void
    {
        Bus::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/reports/batch', [
            'reports' => [
                ['type' => 'user_activity', 'format' => ReportFormat::Screen->value, 'delivery' => ReportDelivery::None->value],
                ['type' => 'app_access', 'format' => ReportFormat::Pdf->value, 'delivery' => ReportDelivery::None->value],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['reports']);
    }

    public function test_batch_rejects_mixed_delivery_modes(): void
    {
        Bus::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/reports/batch', [
            'reports' => [
                ['type' => 'user_activity', 'format' => ReportFormat::Screen->value, 'delivery' => ReportDelivery::None->value],
                ['type' => 'app_access', 'format' => ReportFormat::Screen->value, 'delivery' => ReportDelivery::Download->value],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['reports']);
    }

    public function test_batch_validates_max_50_reports(): void
    {
        Bus::fake();

        $user = User::factory()->create();

        $reports = array_fill(0, 51, [
            'type' => 'user_activity',
            'format' => ReportFormat::Screen->value,
            'delivery' => ReportDelivery::None->value,
        ]);

        $response = $this->actingAs($user)->postJson('/api/reports/batch', [
            'reports' => $reports,
        ]);

        $response->assertUnprocessable();
    }
}
