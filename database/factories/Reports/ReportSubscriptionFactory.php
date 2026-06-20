<?php

namespace Database\Factories\Reports;

use App\Models\Tenant\ReportSubscription;
use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportFrequency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportSubscription>
 */
class ReportSubscriptionFactory extends Factory
{
    protected $model = ReportSubscription::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['user_activity', 'app_access', 'audit_log']),
            'format' => fake()->randomElement(ReportFormat::cases())->value,
            'frequency' => fake()->randomElement(ReportFrequency::cases())->value,
            'delivery' => fake()->randomElement([ReportDelivery::Email, ReportDelivery::S3, ReportDelivery::EmailAndS3])->value,
            'recipients' => [fake()->safeEmail()],
            's3_path' => null,
            'is_active' => true,
            'last_dispatched_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function daily(): static
    {
        return $this->state(['frequency' => ReportFrequency::Daily->value]);
    }

    public function weekly(): static
    {
        return $this->state(['frequency' => ReportFrequency::Weekly->value]);
    }

    public function monthly(): static
    {
        return $this->state(['frequency' => ReportFrequency::Monthly->value]);
    }

    public function emailDelivery(): static
    {
        return $this->state(['delivery' => ReportDelivery::Email->value]);
    }

    public function s3Delivery(): static
    {
        return $this->state([
            'delivery' => ReportDelivery::S3->value,
            's3_path' => 'tenant-reports/',
        ]);
    }
}
