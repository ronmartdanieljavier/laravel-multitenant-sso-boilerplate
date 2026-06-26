<?php

namespace App\Admin\Tests;

use App\Admin\Data\TenantSettingsData;
use App\Admin\Services\TenantSettingsService;
use App\Models\Central\SystemSetting;
use App\Models\Central\Tenant;
use App\Models\Central\TenantSetting;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TenantSettingsServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private TenantSettingsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TenantSettingsService::class);
    }

    private function tenant(): Tenant
    {
        return Tenant::factory()->create(['slug' => 'acme']);
    }

    public function test_get_settings_returns_dto_instance(): void
    {
        $tenant = $this->tenant();
        $result = $this->service->getSettings($tenant->id);

        $this->assertInstanceOf(TenantSettingsData::class, $result);
    }

    public function test_get_settings_returns_null_for_unset_keys(): void
    {
        $tenant = $this->tenant();
        $settings = $this->service->getSettings($tenant->id);

        $this->assertNull($settings->emailDriver);
        $this->assertNull($settings->storageDriver);
        $this->assertNull($settings->reportPdfHeaderText);
    }

    public function test_update_settings_persists_values(): void
    {
        $tenant = $this->tenant();

        $this->service->updateSettings($tenant->id, [
            'email_driver' => 'ses',
            'ses_key' => 'AKIATEST',
            'report_queue' => 'custom-queue',
        ]);

        $settings = $this->service->getSettings($tenant->id);

        $this->assertSame('ses', $settings->emailDriver);
        $this->assertSame('AKIATEST', $settings->sesKey);
        $this->assertSame('custom-queue', $settings->reportQueue);
    }

    public function test_update_settings_removes_null_values(): void
    {
        $tenant = $this->tenant();

        TenantSetting::create(['tenant_id' => $tenant->id, 'key' => 'email_driver', 'value' => 'smtp']);

        $this->service->updateSettings($tenant->id, ['email_driver' => null]);

        $this->assertDatabaseMissing('tenant_settings', [
            'tenant_id' => $tenant->id,
            'key' => 'email_driver',
        ]);
    }

    public function test_effective_email_driver_falls_back_to_system_setting(): void
    {
        SystemSetting::set('email_driver', 'postmark');

        $tenant = $this->tenant();
        $settings = $this->service->getSettings($tenant->id);

        $this->assertNull($settings->emailDriver);
        $this->assertSame('postmark', $settings->effectiveEmailDriver);
    }

    public function test_effective_email_driver_uses_tenant_override(): void
    {
        SystemSetting::set('email_driver', 'postmark');

        $tenant = $this->tenant();
        TenantSetting::create(['tenant_id' => $tenant->id, 'key' => 'email_driver', 'value' => 'ses']);

        $settings = $this->service->getSettings($tenant->id);

        $this->assertSame('ses', $settings->emailDriver);
        $this->assertSame('ses', $settings->effectiveEmailDriver);
    }

    public function test_settings_are_isolated_per_tenant(): void
    {
        $t1 = Tenant::factory()->create(['slug' => 't1']);
        $t2 = Tenant::factory()->create(['slug' => 't2']);

        $this->service->updateSettings($t1->id, ['email_driver' => 'mailgun']);

        $settings2 = $this->service->getSettings($t2->id);
        $this->assertNull($settings2->emailDriver);
    }

    public function test_unknown_keys_are_ignored(): void
    {
        $tenant = $this->tenant();

        $this->service->updateSettings($tenant->id, [
            'email_driver' => 'smtp',
            'unknown_key' => 'should-be-ignored',
        ]);

        $this->assertDatabaseMissing('tenant_settings', [
            'tenant_id' => $tenant->id,
            'key' => 'unknown_key',
        ]);
    }

    public function test_resolve_mail_config_returns_tenant_smtp_config(): void
    {
        $tenant = $this->tenant();

        $this->service->updateSettings($tenant->id, [
            'email_driver' => 'smtp',
            'smtp_host' => 'smtp.acme.com',
            'smtp_port' => '465',
            'smtp_encryption' => 'ssl',
        ]);

        $config = $this->service->resolveMailConfig($tenant->id);

        $this->assertSame('smtp', $config['transport']);
        $this->assertSame('smtp.acme.com', $config['host']);
        $this->assertSame(465, $config['port']);
    }

    public function test_resolve_mail_config_falls_back_to_system_settings(): void
    {
        SystemSetting::set('email_driver', 'postmark');
        SystemSetting::set('postmark_token', 'system-token');

        $tenant = $this->tenant();
        $config = $this->service->resolveMailConfig($tenant->id);

        $this->assertSame('postmark', $config['transport']);
        $this->assertSame('system-token', $config['token']);
    }

    public function test_resolve_upload_constraints_returns_hardcoded_defaults_when_nothing_is_set(): void
    {
        $tenant = $this->tenant();
        $constraints = $this->service->resolveUploadConstraints($tenant->id);

        $this->assertEqualsCanonicalizing(
            ['pdf', 'doc', 'text', 'excel', 'image', 'csv'],
            $constraints['allowedTypes']
        );

        foreach (['pdf', 'doc', 'text', 'excel', 'image', 'csv'] as $type) {
            $this->assertSame(5, $constraints['maxSizes'][$type]);
        }
    }

    public function test_resolve_upload_constraints_uses_system_setting_over_hardcoded_default(): void
    {
        SystemSetting::set('upload_allowed_types', 'pdf,image');
        SystemSetting::set('upload_max_size_pdf', '20');
        SystemSetting::set('upload_max_size_image', '3');

        $tenant = $this->tenant();
        $constraints = $this->service->resolveUploadConstraints($tenant->id);

        $this->assertEqualsCanonicalizing(['pdf', 'image'], $constraints['allowedTypes']);
        $this->assertSame(20, $constraints['maxSizes']['pdf']);
        $this->assertSame(3, $constraints['maxSizes']['image']);
        $this->assertArrayNotHasKey('doc', $constraints['maxSizes']);
    }

    public function test_resolve_upload_constraints_uses_tenant_override_over_system_setting(): void
    {
        SystemSetting::set('upload_allowed_types', 'pdf,doc,image');
        SystemSetting::set('upload_max_size_pdf', '10');

        $tenant = $this->tenant();
        $this->service->updateSettings($tenant->id, [
            'upload_allowed_types' => 'pdf',
            'upload_max_size_pdf' => '15',
        ]);

        $constraints = $this->service->resolveUploadConstraints($tenant->id);

        $this->assertSame(['pdf'], $constraints['allowedTypes']);
        $this->assertSame(15, $constraints['maxSizes']['pdf']);
        $this->assertArrayNotHasKey('doc', $constraints['maxSizes']);
    }

    public function test_resolve_upload_constraints_only_includes_max_sizes_for_allowed_types(): void
    {
        $tenant = $this->tenant();
        $this->service->updateSettings($tenant->id, [
            'upload_allowed_types' => 'pdf,csv',
        ]);

        $constraints = $this->service->resolveUploadConstraints($tenant->id);

        $this->assertSame(['pdf', 'csv'], $constraints['allowedTypes']);
        $this->assertArrayHasKey('pdf', $constraints['maxSizes']);
        $this->assertArrayHasKey('csv', $constraints['maxSizes']);
        $this->assertArrayNotHasKey('image', $constraints['maxSizes']);
        $this->assertArrayNotHasKey('excel', $constraints['maxSizes']);
    }

    public function test_effective_upload_allowed_types_falls_back_to_system_setting(): void
    {
        SystemSetting::set('upload_allowed_types', 'pdf,image');

        $tenant = $this->tenant();
        $settings = $this->service->getSettings($tenant->id);

        $this->assertNull($settings->uploadAllowedTypes);
        $this->assertSame('pdf,image', $settings->effectiveUploadAllowedTypes);
    }

    public function test_effective_upload_max_size_uses_tenant_override(): void
    {
        SystemSetting::set('upload_max_size_pdf', '10');

        $tenant = $this->tenant();
        $this->service->updateSettings($tenant->id, ['upload_max_size_pdf' => '25']);

        $settings = $this->service->getSettings($tenant->id);

        $this->assertSame('25', $settings->uploadMaxSizePdf);
        $this->assertSame('25', $settings->effectiveUploadMaxSizePdf);
    }
}
