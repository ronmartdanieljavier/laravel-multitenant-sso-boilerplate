<?php

namespace Database\Factories\Reports;

use App\Models\Central\Report;
use App\Models\Central\User;
use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tenant_id' => null,
            'type' => fake()->randomElement(['user_activity', 'app_access', 'audit_log']),
            'format' => fake()->randomElement(ReportFormat::cases())->value,
            'delivery' => fake()->randomElement(ReportDelivery::cases())->value,
            'status' => ReportStatus::Pending->value,
            'parameters' => null,
            'file_path' => null,
            'error_message' => null,
            'batch_id' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => ReportStatus::Pending->value]);
    }

    public function processing(): static
    {
        return $this->state([
            'status' => ReportStatus::Processing->value,
            'started_at' => now(),
        ]);
    }

    public function success(): static
    {
        return $this->state([
            'status' => ReportStatus::Success->value,
            'file_path' => 'reports/1/test_report.pdf',
            'started_at' => now()->subSeconds(5),
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => ReportStatus::Failed->value,
            'error_message' => 'An error occurred during report generation.',
            'started_at' => now()->subSeconds(3),
            'completed_at' => now(),
        ]);
    }

    public function screen(): static
    {
        return $this->state(['format' => ReportFormat::Screen->value]);
    }

    public function pdf(): static
    {
        return $this->state(['format' => ReportFormat::Pdf->value]);
    }

    public function excel(): static
    {
        return $this->state(['format' => ReportFormat::Excel->value]);
    }
}
