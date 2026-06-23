<?php

namespace App\Admin\Tests;

use App\Models\Central\SystemSetting;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SystemSettingsWebTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('admin.settings'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_settings_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Settings/Index')
                ->has('settings')
            );
    }

    public function test_settings_payload_includes_all_expected_keys(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.settings'))
            ->assertInertia(fn ($page) => $page
                ->has('settings', fn ($settings) => $settings
                    ->has('email_driver')
                    ->has('smtp_host')
                    ->has('smtp_port')
                    ->has('postmark_token')
                    ->has('authentication_idle_time')
                    ->has('s3_key')
                    ->has('s3_bucket')
                    ->has('s3_region')
                    ->has('s3_secret')
                    ->has('s3_url')
                    ->etc()
                )
            );
    }

    public function test_unauthenticated_user_cannot_update_settings(): void
    {
        $this->put(route('admin.settings.update'), ['authentication_idle_time' => 30])
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_update_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'email_driver' => 'smtp',
                'smtp_host' => 'smtp.example.com',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'smtp_from_address' => 'no-reply@example.com',
                'smtp_from_name' => 'Example',
                'authentication_idle_time' => 60,
                'storage_driver' => 's3',
                's3_key' => 'AKIAIOSFODNN7EXAMPLE',
                's3_secret' => 'wJalrXUtnFEMI/K7MDENG',
                's3_region' => 'us-east-1',
                's3_bucket' => 'my-bucket',
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertSame('smtp', SystemSetting::get('email_driver'));
        $this->assertSame('smtp.example.com', SystemSetting::get('smtp_host'));
        $this->assertSame('60', SystemSetting::get('authentication_idle_time'));
        $this->assertSame('s3', SystemSetting::get('storage_driver'));
        $this->assertSame('my-bucket', SystemSetting::get('s3_bucket'));
    }

    public function test_authenticated_user_can_save_fcm_push_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'push_driver' => 'fcm',
                'fcm_project_id' => 'my-firebase-project',
                'fcm_server_key' => 'AAAAxxxxx:APA91...',
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertSame('fcm', SystemSetting::get('push_driver'));
        $this->assertSame('my-firebase-project', SystemSetting::get('fcm_project_id'));
    }

    public function test_authenticated_user_can_save_apns_push_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'push_driver' => 'apns',
                'apns_key_id' => 'ABCDE12345',
                'apns_team_id' => 'FGHIJ67890',
                'apns_bundle_id' => 'com.example.myapp',
                'apns_environment' => 'production',
                'apns_private_key' => "-----BEGIN PRIVATE KEY-----\nfakekey\n-----END PRIVATE KEY-----",
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertSame('apns', SystemSetting::get('push_driver'));
        $this->assertSame('production', SystemSetting::get('apns_environment'));
    }

    public function test_authenticated_user_can_save_onesignal_push_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'push_driver' => 'onesignal',
                'onesignal_app_id' => 'app-uuid-abc',
                'onesignal_rest_api_key' => 'rest-key-xyz',
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertSame('onesignal', SystemSetting::get('push_driver'));
        $this->assertSame('app-uuid-abc', SystemSetting::get('onesignal_app_id'));
    }

    public function test_update_rejects_invalid_push_driver(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), ['push_driver' => 'pusher'])
            ->assertSessionHasErrors('push_driver');
    }

    public function test_update_rejects_invalid_apns_environment(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), ['apns_environment' => 'staging'])
            ->assertSessionHasErrors('apns_environment');
    }

    public function test_authenticated_user_can_save_security_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'password_min_length' => 12,
                'password_require_uppercase' => true,
                'password_require_digit' => true,
                'password_require_symbol' => false,
                'password_expiry_days' => 90,
                'two_factor_auth' => 'optional',
                'session_concurrency_limit' => 3,
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertSame('12', SystemSetting::get('password_min_length'));
        $this->assertSame('90', SystemSetting::get('password_expiry_days'));
        $this->assertSame('optional', SystemSetting::get('two_factor_auth'));
        $this->assertSame('3', SystemSetting::get('session_concurrency_limit'));
    }

    public function test_update_rejects_invalid_two_factor_auth_value(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), ['two_factor_auth' => 'mandatory'])
            ->assertSessionHasErrors('two_factor_auth');
    }

    public function test_update_rejects_password_min_length_below_6(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), ['password_min_length' => 4])
            ->assertSessionHasErrors('password_min_length');
    }

    public function test_authenticated_user_can_save_branding_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'app_name' => 'My SaaS',
                'support_email' => 'support@example.com',
                'support_url' => 'https://help.example.com',
                'logo_url' => 'https://cdn.example.com/logo.png',
                'favicon_url' => 'https://cdn.example.com/favicon.ico',
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertSame('My SaaS', SystemSetting::get('app_name'));
        $this->assertSame('support@example.com', SystemSetting::get('support_email'));
        $this->assertSame('https://cdn.example.com/logo.png', SystemSetting::get('logo_url'));
    }

    public function test_update_rejects_invalid_support_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), ['support_email' => 'not-an-email'])
            ->assertSessionHasErrors('support_email');
    }

    public function test_authenticated_user_can_save_twilio_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'sms_driver' => 'twilio',
                'twilio_sid' => 'ACxxxxxxxxxxxxx',
                'twilio_token' => 'auth-token-abc',
                'twilio_from' => '+15550001234',
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertSame('twilio', SystemSetting::get('sms_driver'));
        $this->assertSame('+15550001234', SystemSetting::get('twilio_from'));
    }

    public function test_authenticated_user_can_save_vonage_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'sms_driver' => 'vonage',
                'vonage_key' => 'abc123',
                'vonage_secret' => 'secret456',
                'vonage_from' => 'MyApp',
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertSame('vonage', SystemSetting::get('sms_driver'));
        $this->assertSame('MyApp', SystemSetting::get('vonage_from'));
    }

    public function test_authenticated_user_can_save_sns_sms_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'sms_driver' => 'sns',
                'sns_sms_key' => 'AKID',
                'sns_sms_secret' => 'SECRET',
                'sns_sms_region' => 'us-east-1',
                'sns_sms_sender_id' => 'MyApp',
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertSame('sns', SystemSetting::get('sms_driver'));
        $this->assertSame('us-east-1', SystemSetting::get('sns_sms_region'));
        $this->assertSame('MyApp', SystemSetting::get('sns_sms_sender_id'));
    }

    public function test_update_rejects_invalid_sms_driver(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), ['sms_driver' => 'messagebird'])
            ->assertSessionHasErrors('sms_driver');
    }

    public function test_update_rejects_sns_sender_id_over_11_chars(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), ['sns_sms_sender_id' => 'TooLongName123'])
            ->assertSessionHasErrors('sns_sms_sender_id');
    }

    public function test_authenticated_user_can_save_r2_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'storage_driver' => 'r2',
                'r2_account_id' => 'abc123def456',
                'r2_access_key' => 'r2-key',
                'r2_secret' => 'r2-secret',
                'r2_bucket' => 'my-r2-bucket',
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertSame('r2', SystemSetting::get('storage_driver'));
        $this->assertSame('abc123def456', SystemSetting::get('r2_account_id'));
        $this->assertSame('my-r2-bucket', SystemSetting::get('r2_bucket'));
    }

    public function test_authenticated_user_can_save_gcs_settings(): void
    {
        $user = User::factory()->create();

        $keyJson = json_encode(['type' => 'service_account', 'project_id' => 'my-project']);

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'storage_driver' => 'gcs',
                'gcs_project_id' => 'my-gcp-project',
                'gcs_key_json' => $keyJson,
                'gcs_bucket' => 'my-gcs-bucket',
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertSame('gcs', SystemSetting::get('storage_driver'));
        $this->assertSame('my-gcp-project', SystemSetting::get('gcs_project_id'));
        $this->assertSame('my-gcs-bucket', SystemSetting::get('gcs_bucket'));
    }

    public function test_authenticated_user_can_save_local_storage(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), ['storage_driver' => 'local'])
            ->assertRedirect(route('admin.settings'));

        $this->assertSame('local', SystemSetting::get('storage_driver'));
    }

    public function test_authenticated_user_can_save_ftp_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'storage_driver' => 'ftp',
                'ftp_host' => 'ftp.example.com',
                'ftp_port' => 21,
                'ftp_username' => 'ftpuser',
                'ftp_password' => 'secret',
                'ftp_root' => '/uploads',
                'ftp_passive' => true,
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertSame('ftp', SystemSetting::get('storage_driver'));
        $this->assertSame('ftp.example.com', SystemSetting::get('ftp_host'));
        $this->assertSame('/uploads', SystemSetting::get('ftp_root'));
    }

    public function test_authenticated_user_can_save_sftp_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'storage_driver' => 'sftp',
                'sftp_host' => 'sftp.example.com',
                'sftp_port' => 22,
                'sftp_username' => 'deploy',
                'sftp_root' => '/var/www/uploads',
                'sftp_private_key' => "-----BEGIN OPENSSH PRIVATE KEY-----\nfakekey\n-----END OPENSSH PRIVATE KEY-----",
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertSame('sftp', SystemSetting::get('storage_driver'));
        $this->assertSame('sftp.example.com', SystemSetting::get('sftp_host'));
        $this->assertSame('/var/www/uploads', SystemSetting::get('sftp_root'));
    }

    public function test_update_rejects_invalid_storage_driver(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), ['storage_driver' => 'dropbox'])
            ->assertSessionHasErrors('storage_driver');
    }

    public function test_authenticated_user_can_save_mailgun_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'email_driver' => 'mailgun',
                'mailgun_domain' => 'mg.example.com',
                'mailgun_secret' => 'key-abc123',
                'mailgun_endpoint' => 'api.eu.mailgun.net',
                'mailgun_from_address' => 'hello@example.com',
                'mailgun_from_name' => 'MyApp',
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertSame('mailgun', SystemSetting::get('email_driver'));
        $this->assertSame('mg.example.com', SystemSetting::get('mailgun_domain'));
        $this->assertSame('api.eu.mailgun.net', SystemSetting::get('mailgun_endpoint'));
    }

    public function test_authenticated_user_can_save_ses_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'email_driver' => 'ses',
                'ses_key' => 'AKIAIOSFODNN7EXAMPLE',
                'ses_secret' => 'wJalrXUtnFEMI',
                'ses_region' => 'us-east-1',
                'ses_from_address' => 'no-reply@example.com',
                'ses_from_name' => 'MyApp',
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertSame('ses', SystemSetting::get('email_driver'));
        $this->assertSame('us-east-1', SystemSetting::get('ses_region'));
    }

    public function test_update_rejects_invalid_email_driver(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), ['email_driver' => 'sendgrid'])
            ->assertSessionHasErrors('email_driver');
    }

    public function test_update_rejects_invalid_mailgun_endpoint(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'email_driver' => 'mailgun',
                'mailgun_endpoint' => 'api.invalid.mailgun.net',
            ])
            ->assertSessionHasErrors('mailgun_endpoint');
    }

    public function test_authenticated_user_can_save_authentication_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'authentication_idle_time' => 30,
                'max_login_attempts' => 5,
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertSame('30', SystemSetting::get('authentication_idle_time'));
        $this->assertSame('5', SystemSetting::get('max_login_attempts'));
    }

    public function test_update_rejects_out_of_range_idle_timeout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), ['authentication_idle_time' => 0])
            ->assertSessionHasErrors('authentication_idle_time');
    }

    public function test_update_rejects_out_of_range_max_login_attempts(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), ['max_login_attempts' => 0])
            ->assertSessionHasErrors('max_login_attempts');
    }

    public function test_missing_required_settings_shared_as_inertia_prop(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.settings'))
            ->assertInertia(fn ($page) => $page
                ->has('missingRequiredSettings')
            );
    }

    public function test_missing_required_settings_is_empty_when_all_set(): void
    {
        $user = User::factory()->create();

        foreach ([
            'email_driver' => 'smtp',
            'authentication_idle_time' => '30',
            'storage_driver' => 's3',
        ] as $key => $value) {
            SystemSetting::set($key, $value);
        }

        $this->actingAs($user)
            ->get(route('admin.settings'))
            ->assertInertia(fn ($page) => $page
                ->where('missingRequiredSettings', [])
            );
    }

    public function test_missing_required_settings_lists_unset_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.settings'))
            ->assertInertia(fn ($page) => $page
                ->where('missingRequiredSettings', fn ($missing) => count($missing) > 0)
            );
    }

    public function test_authenticated_user_can_save_email_footer_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'email_footer_signature' => '© 2025 My SaaS. All rights reserved.',
                'email_footer_unsubscribe_url' => 'https://example.com/unsubscribe',
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertSame('© 2025 My SaaS. All rights reserved.', SystemSetting::get('email_footer_signature'));
        $this->assertSame('https://example.com/unsubscribe', SystemSetting::get('email_footer_unsubscribe_url'));
    }

    public function test_authenticated_user_can_save_html_email_footer_signature(): void
    {
        $user = User::factory()->create();
        $html = '<p><strong>© 2025 My SaaS.</strong> <a href="https://example.com">Visit us</a></p>';

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'email_footer_signature' => $html,
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertSame($html, SystemSetting::get('email_footer_signature'));
    }

    public function test_email_footer_signature_can_be_cleared(): void
    {
        $user = User::factory()->create();
        SystemSetting::set('email_footer_signature', '<p>Old footer</p>');

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'email_footer_signature' => null,
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertNull(SystemSetting::get('email_footer_signature'));
    }

    public function test_update_rejects_email_footer_signature_over_2000_chars(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'email_footer_signature' => str_repeat('a', 2001),
            ])
            ->assertSessionHasErrors('email_footer_signature');
    }

    public function test_update_rejects_invalid_email_footer_unsubscribe_url(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'email_footer_unsubscribe_url' => 'not-a-valid-url',
            ])
            ->assertSessionHasErrors('email_footer_unsubscribe_url');
    }
}
