<?php

namespace Tests\Feature\Reports;

use App\Console\Commands\DispatchScheduledReportsCommand;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Tenant\ReportSubscription;
use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportFrequency;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReportSubscriptionTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
        ]);
    }

    // ── API CRUD ──────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_list_subscriptions(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/reports/subscriptions');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'current_page', 'total']);
    }

    public function test_unauthenticated_user_cannot_list_subscriptions(): void
    {
        $this->getJson('/api/reports/subscriptions')->assertUnauthorized();
    }

    public function test_authenticated_user_can_create_subscription(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/reports/subscriptions', [
            'type' => 'user_activity',
            'format' => ReportFormat::Screen->value,
            'frequency' => ReportFrequency::Daily->value,
            'delivery' => ReportDelivery::Email->value,
            'recipients' => ['admin@example.com'],
        ]);

        $response->assertCreated();
        $response->assertJsonFragment([
            'type' => 'user_activity',
            'frequency' => ReportFrequency::Daily->value,
            'delivery' => ReportDelivery::Email->value,
        ]);
    }

    public function test_subscription_creation_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/reports/subscriptions', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'format', 'frequency', 'delivery']);
    }

    public function test_subscription_creation_validates_recipient_emails(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/reports/subscriptions', [
            'type' => 'audit_log',
            'format' => ReportFormat::Screen->value,
            'frequency' => ReportFrequency::Daily->value,
            'delivery' => ReportDelivery::Email->value,
            'recipients' => ['not-an-email'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['recipients.0']);
    }

    public function test_authenticated_user_can_update_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = ReportSubscription::on('tenant')->create([
            'type' => 'user_activity',
            'format' => ReportFormat::Screen->value,
            'frequency' => ReportFrequency::Daily->value,
            'delivery' => ReportDelivery::Email->value,
            'recipients' => ['admin@example.com'],
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->putJson("/api/reports/subscriptions/{$subscription->id}", [
            'is_active' => false,
            'frequency' => ReportFrequency::Weekly->value,
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['is_active' => false, 'frequency' => ReportFrequency::Weekly->value]);
    }

    public function test_authenticated_user_can_delete_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = ReportSubscription::on('tenant')->create([
            'type' => 'user_activity',
            'format' => ReportFormat::Screen->value,
            'frequency' => ReportFrequency::Daily->value,
            'delivery' => ReportDelivery::None->value,
        ]);

        $this->actingAs($user)->deleteJson("/api/reports/subscriptions/{$subscription->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('report_subscriptions', ['id' => $subscription->id], 'tenant');
    }

    // ── ReportFrequency::isDue ────────────────────────────────────────────────

    public function test_daily_subscription_is_due_when_never_dispatched(): void
    {
        $subscription = new ReportSubscription(['frequency' => ReportFrequency::Daily]);

        $this->assertTrue($subscription->isDue());
    }

    public function test_daily_subscription_is_due_after_yesterday(): void
    {
        $subscription = new ReportSubscription([
            'frequency' => ReportFrequency::Daily,
            'last_dispatched_at' => now()->subDay(),
        ]);

        $this->assertTrue($subscription->isDue());
    }

    public function test_daily_subscription_is_not_due_when_dispatched_today(): void
    {
        $subscription = new ReportSubscription([
            'frequency' => ReportFrequency::Daily,
            'last_dispatched_at' => now(),
        ]);

        $this->assertFalse($subscription->isDue());
    }

    public function test_weekly_subscription_is_due_after_last_week(): void
    {
        $subscription = new ReportSubscription([
            'frequency' => ReportFrequency::Weekly,
            'last_dispatched_at' => now()->subWeek(),
        ]);

        $this->assertTrue($subscription->isDue());
    }

    public function test_monthly_subscription_is_due_after_last_month(): void
    {
        $subscription = new ReportSubscription([
            'frequency' => ReportFrequency::Monthly,
            'last_dispatched_at' => now()->subMonth(),
        ]);

        $this->assertTrue($subscription->isDue());
    }

    // ── Dispatch command ──────────────────────────────────────────────────────

    public function test_dispatch_command_skips_inactive_tenants(): void
    {
        Queue::fake();

        Tenant::factory()->create(['is_active' => false]);

        $this->artisan(DispatchScheduledReportsCommand::class)
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_dispatch_command_reports_no_active_tenants(): void
    {
        $this->artisan(DispatchScheduledReportsCommand::class)
            ->assertSuccessful();
    }
}
