<?php

namespace App\Admin\Tests;

use App\Admin\Data\MissingSystemSettingsData;
use App\Admin\Data\SystemSettingsData;
use App\Admin\Services\SystemSettingsService;
use App\Models\Central\SystemSetting;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SystemSettingsServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private SystemSettingsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SystemSettingsService::class);
    }

    public function test_get_settings_returns_system_settings_data(): void
    {
        $result = $this->service->getSettings();

        $this->assertInstanceOf(SystemSettingsData::class, $result);
    }

    public function test_get_settings_reflects_persisted_values(): void
    {
        SystemSetting::set('email_driver', 'postmark');
        SystemSetting::set('storage_driver', 's3');

        $result = $this->service->getSettings();

        $this->assertSame('postmark', $result->emailDriver);
        $this->assertSame('s3', $result->storageDriver);
    }

    public function test_get_settings_returns_null_for_unset_keys(): void
    {
        $result = $this->service->getSettings();

        $this->assertNull($result->smtpHost);
        $this->assertNull($result->twilioSid);
    }

    public function test_get_missing_required_settings_returns_missing_system_settings_data(): void
    {
        $result = $this->service->getMissingRequiredSettings();

        $this->assertInstanceOf(MissingSystemSettingsData::class, $result);
    }

    public function test_get_missing_required_settings_lists_all_when_none_set(): void
    {
        $result = $this->service->getMissingRequiredSettings();

        $this->assertContains('Default email service', $result->labels);
        $this->assertContains('Authentication idle timeout', $result->labels);
        $this->assertContains('Default storage provider', $result->labels);
    }

    public function test_get_missing_required_settings_is_empty_when_all_required_are_set(): void
    {
        SystemSetting::set('email_driver', 'smtp');
        SystemSetting::set('authentication_idle_time', '30');
        SystemSetting::set('storage_driver', 's3');

        $result = $this->service->getMissingRequiredSettings();

        $this->assertEmpty($result->labels);
    }

    public function test_update_settings_persists_values(): void
    {
        $this->service->updateSettings([
            'email_driver' => 'mailgun',
            'mailgun_domain' => 'mg.example.com',
        ]);

        $this->assertSame('mailgun', SystemSetting::get('email_driver'));
        $this->assertSame('mg.example.com', SystemSetting::get('mailgun_domain'));
    }

    public function test_update_settings_are_reflected_in_get_settings(): void
    {
        $this->service->updateSettings(['app_name' => 'My SaaS']);

        $result = $this->service->getSettings();

        $this->assertSame('My SaaS', $result->appName);
    }
}
