<?php

namespace App\Admin\Tests;

use App\Models\Central\SystemSetting;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SystemSettingsApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/admin/settings')->assertUnauthorized();
    }

    public function test_authenticated_user_can_retrieve_settings(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'email_driver',
                    'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
                    'smtp_encryption', 'smtp_from_address', 'smtp_from_name',
                    'postmark_token', 'postmark_from_address', 'postmark_from_name',
                    'mailgun_domain', 'mailgun_secret', 'mailgun_endpoint',
                    'mailgun_from_address', 'mailgun_from_name',
                    'ses_key', 'ses_secret', 'ses_region', 'ses_from_address', 'ses_from_name',
                    'authentication_idle_time',
                    'storage_driver',
                    's3_key', 's3_secret', 's3_region', 's3_bucket', 's3_url',
                    'r2_account_id', 'r2_access_key', 'r2_secret', 'r2_bucket', 'r2_url',
                    'gcs_project_id', 'gcs_key_json', 'gcs_bucket', 'gcs_url',
                ],
                'missing_required',
            ]);
    }

    public function test_authenticated_user_can_update_settings(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', [
            'email_driver' => 'postmark',
            'postmark_token' => 'pm-token-abc',
            'postmark_from_address' => 'hello@example.com',
            'postmark_from_name' => 'MyApp',
            'authentication_idle_time' => 45,
            'storage_driver' => 's3',
            's3_key' => 'AKID',
            's3_secret' => 'SECRET',
            's3_region' => 'ap-southeast-1',
            's3_bucket' => 'app-bucket',
        ])
            ->assertOk()
            ->assertJsonPath('data.email_driver', 'postmark')
            ->assertJsonPath('data.storage_driver', 's3')
            ->assertJsonPath('data.authentication_idle_time', '45');

        $this->assertSame('postmark', SystemSetting::get('email_driver'));
        $this->assertSame('s3', SystemSetting::get('storage_driver'));
        $this->assertSame('app-bucket', SystemSetting::get('s3_bucket'));
    }

    public function test_authenticated_user_can_save_push_settings(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', [
            'push_driver' => 'fcm',
            'fcm_project_id' => 'my-firebase-project',
            'fcm_server_key' => 'AAAAxxxxx:APA91...',
        ])
            ->assertOk()
            ->assertJsonPath('data.push_driver', 'fcm')
            ->assertJsonPath('data.fcm_project_id', 'my-firebase-project');

        $this->assertSame('fcm', SystemSetting::get('push_driver'));
    }

    public function test_update_rejects_invalid_push_driver(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', ['push_driver' => 'pusher'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('push_driver');
    }

    public function test_authenticated_user_can_save_security_settings(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', [
            'password_min_length' => 12,
            'password_require_uppercase' => true,
            'password_require_digit' => true,
            'password_expiry_days' => 90,
            'two_factor_auth' => 'required',
            'session_concurrency_limit' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('data.password_min_length', '12')
            ->assertJsonPath('data.two_factor_auth', 'required');

        $this->assertSame('required', SystemSetting::get('two_factor_auth'));
    }

    public function test_update_rejects_invalid_two_factor_auth_value(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', ['two_factor_auth' => 'mandatory'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('two_factor_auth');
    }

    public function test_authenticated_user_can_save_branding_settings(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', [
            'app_name' => 'My SaaS',
            'support_email' => 'support@example.com',
            'support_url' => 'https://help.example.com',
            'logo_url' => 'https://cdn.example.com/logo.png',
        ])
            ->assertOk()
            ->assertJsonPath('data.app_name', 'My SaaS')
            ->assertJsonPath('data.support_email', 'support@example.com');

        $this->assertSame('My SaaS', SystemSetting::get('app_name'));
    }

    public function test_update_rejects_invalid_branding_support_email(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', ['support_email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('support_email');
    }

    public function test_authenticated_user_can_save_twilio_settings(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', [
            'sms_driver' => 'twilio',
            'twilio_sid' => 'ACxxxxxxxxxxxxx',
            'twilio_token' => 'auth-token-abc',
            'twilio_from' => '+15550001234',
        ])
            ->assertOk()
            ->assertJsonPath('data.sms_driver', 'twilio')
            ->assertJsonPath('data.twilio_from', '+15550001234');

        $this->assertSame('twilio', SystemSetting::get('sms_driver'));
    }

    public function test_authenticated_user_can_save_vonage_settings(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', [
            'sms_driver' => 'vonage',
            'vonage_key' => 'abc123',
            'vonage_secret' => 'secret456',
            'vonage_from' => 'MyApp',
        ])
            ->assertOk()
            ->assertJsonPath('data.sms_driver', 'vonage')
            ->assertJsonPath('data.vonage_from', 'MyApp');
    }

    public function test_authenticated_user_can_save_sns_sms_settings(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', [
            'sms_driver' => 'sns',
            'sns_sms_key' => 'AKID',
            'sns_sms_secret' => 'SECRET',
            'sns_sms_region' => 'us-east-1',
            'sns_sms_sender_id' => 'MyApp',
        ])
            ->assertOk()
            ->assertJsonPath('data.sms_driver', 'sns')
            ->assertJsonPath('data.sns_sms_sender_id', 'MyApp');
    }

    public function test_update_rejects_invalid_sms_driver(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', ['sms_driver' => 'messagebird'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sms_driver');
    }

    public function test_update_rejects_sns_sender_id_over_11_chars(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', ['sns_sms_sender_id' => 'TooLongName123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sns_sms_sender_id');
    }

    public function test_authenticated_user_can_save_r2_settings(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', [
            'storage_driver' => 'r2',
            'r2_account_id' => 'abc123def456',
            'r2_access_key' => 'r2-key',
            'r2_secret' => 'r2-secret',
            'r2_bucket' => 'my-r2-bucket',
        ])
            ->assertOk()
            ->assertJsonPath('data.storage_driver', 'r2')
            ->assertJsonPath('data.r2_account_id', 'abc123def456')
            ->assertJsonPath('data.r2_bucket', 'my-r2-bucket');

        $this->assertSame('r2', SystemSetting::get('storage_driver'));
    }

    public function test_authenticated_user_can_save_gcs_settings(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $keyJson = json_encode(['type' => 'service_account', 'project_id' => 'my-project']);

        $this->putJson('/api/v1/admin/settings', [
            'storage_driver' => 'gcs',
            'gcs_project_id' => 'my-gcp-project',
            'gcs_key_json' => $keyJson,
            'gcs_bucket' => 'my-gcs-bucket',
        ])
            ->assertOk()
            ->assertJsonPath('data.storage_driver', 'gcs')
            ->assertJsonPath('data.gcs_project_id', 'my-gcp-project')
            ->assertJsonPath('data.gcs_bucket', 'my-gcs-bucket');

        $this->assertSame('gcs', SystemSetting::get('storage_driver'));
    }

    public function test_authenticated_user_can_save_local_storage(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', ['storage_driver' => 'local'])
            ->assertOk()
            ->assertJsonPath('data.storage_driver', 'local');

        $this->assertSame('local', SystemSetting::get('storage_driver'));
    }

    public function test_authenticated_user_can_save_ftp_settings(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', [
            'storage_driver' => 'ftp',
            'ftp_host' => 'ftp.example.com',
            'ftp_port' => 21,
            'ftp_username' => 'ftpuser',
            'ftp_password' => 'secret',
            'ftp_root' => '/uploads',
            'ftp_passive' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.storage_driver', 'ftp')
            ->assertJsonPath('data.ftp_host', 'ftp.example.com');

        $this->assertSame('ftp', SystemSetting::get('storage_driver'));
    }

    public function test_authenticated_user_can_save_sftp_settings(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', [
            'storage_driver' => 'sftp',
            'sftp_host' => 'sftp.example.com',
            'sftp_port' => 22,
            'sftp_username' => 'deploy',
            'sftp_root' => '/var/www/uploads',
        ])
            ->assertOk()
            ->assertJsonPath('data.storage_driver', 'sftp')
            ->assertJsonPath('data.sftp_host', 'sftp.example.com');

        $this->assertSame('sftp', SystemSetting::get('storage_driver'));
    }

    public function test_update_rejects_invalid_storage_driver(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', ['storage_driver' => 'dropbox'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('storage_driver');
    }

    public function test_authenticated_user_can_save_mailgun_settings(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', [
            'email_driver' => 'mailgun',
            'mailgun_domain' => 'mg.example.com',
            'mailgun_secret' => 'key-abc123',
            'mailgun_endpoint' => 'api.mailgun.net',
            'mailgun_from_address' => 'hello@example.com',
            'mailgun_from_name' => 'MyApp',
        ])
            ->assertOk()
            ->assertJsonPath('data.email_driver', 'mailgun')
            ->assertJsonPath('data.mailgun_domain', 'mg.example.com');
    }

    public function test_authenticated_user_can_save_ses_settings(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', [
            'email_driver' => 'ses',
            'ses_key' => 'AKID',
            'ses_secret' => 'SECRET',
            'ses_region' => 'ap-southeast-1',
            'ses_from_address' => 'no-reply@example.com',
            'ses_from_name' => 'MyApp',
        ])
            ->assertOk()
            ->assertJsonPath('data.email_driver', 'ses')
            ->assertJsonPath('data.ses_region', 'ap-southeast-1');
    }

    public function test_update_rejects_invalid_email_driver(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', ['email_driver' => 'sendgrid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email_driver');
    }

    public function test_update_rejects_invalid_mailgun_endpoint(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', [
            'email_driver' => 'mailgun',
            'mailgun_endpoint' => 'api.invalid.mailgun.net',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mailgun_endpoint');
    }

    public function test_authenticated_user_can_save_authentication_settings(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', [
            'authentication_idle_time' => 30,
            'max_login_attempts' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('data.authentication_idle_time', '30')
            ->assertJsonPath('data.max_login_attempts', '5');

        $this->assertSame('5', SystemSetting::get('max_login_attempts'));
    }

    public function test_update_rejects_invalid_idle_timeout(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', ['authentication_idle_time' => 9999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('authentication_idle_time');
    }

    public function test_update_rejects_invalid_max_login_attempts(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', ['max_login_attempts' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('max_login_attempts');
    }

    public function test_missing_required_is_empty_when_all_required_settings_set(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        foreach ([
            'email_driver' => 'smtp',
            'authentication_idle_time' => '30',
            'storage_driver' => 's3',
        ] as $key => $value) {
            SystemSetting::set($key, $value);
        }

        $this->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonPath('missing_required', []);
    }

    public function test_missing_required_lists_unset_fields(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $response = $this->getJson('/api/v1/admin/settings')->assertOk();
        $this->assertNotEmpty($response->json('missing_required'));
    }

    public function test_unauthenticated_put_returns_401(): void
    {
        $this->putJson('/api/v1/admin/settings', ['email_driver' => 'smtp'])->assertUnauthorized();
    }

    public function test_authenticated_user_can_save_email_footer_settings(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', [
            'email_footer_signature' => '© 2025 My SaaS. All rights reserved.',
            'email_footer_unsubscribe_url' => 'https://example.com/unsubscribe',
        ])
            ->assertOk()
            ->assertJsonPath('data.email_footer_signature', '© 2025 My SaaS. All rights reserved.')
            ->assertJsonPath('data.email_footer_unsubscribe_url', 'https://example.com/unsubscribe');

        $this->assertSame('© 2025 My SaaS. All rights reserved.', SystemSetting::get('email_footer_signature'));
    }

    public function test_authenticated_user_can_save_html_email_footer_signature(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));
        $html = '<p><strong>© 2025 My SaaS.</strong> <a href="https://example.com">Visit us</a></p>';

        $this->putJson('/api/v1/admin/settings', ['email_footer_signature' => $html])
            ->assertOk()
            ->assertJsonPath('data.email_footer_signature', $html);

        $this->assertSame($html, SystemSetting::get('email_footer_signature'));
    }

    public function test_email_footer_signature_can_be_cleared(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));
        SystemSetting::set('email_footer_signature', '<p>Old footer</p>');

        $this->putJson('/api/v1/admin/settings', ['email_footer_signature' => null])
            ->assertOk()
            ->assertJsonPath('data.email_footer_signature', null);

        $this->assertNull(SystemSetting::get('email_footer_signature'));
    }

    public function test_update_rejects_email_footer_signature_over_2000_chars(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', [
            'email_footer_signature' => str_repeat('a', 2001),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email_footer_signature');
    }

    public function test_update_rejects_invalid_email_footer_unsubscribe_url(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->putJson('/api/v1/admin/settings', [
            'email_footer_unsubscribe_url' => 'not-a-valid-url',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email_footer_unsubscribe_url');
    }
}
