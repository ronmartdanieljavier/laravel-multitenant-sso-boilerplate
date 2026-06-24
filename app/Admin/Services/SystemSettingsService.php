<?php

namespace App\Admin\Services;

use App\Admin\Data\MissingSystemSettingsData;
use App\Admin\Data\SystemSettingsData;
use App\Models\Central\SystemSetting;

class SystemSettingsService
{
    /**
     * @var array<string, string>
     */
    private const REQUIRED_SETTINGS = [
        'email_driver' => 'Default email service',
        'authentication_idle_time' => 'Authentication idle timeout',
        'storage_driver' => 'Default storage provider',
    ];

    /**
     * Get all system settings as a structured data object.
     */
    public function getSettings(): SystemSettingsData
    {
        $keys = [
            'email_driver',
            'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
            'smtp_encryption', 'smtp_from_address', 'smtp_from_name',
            'postmark_token', 'postmark_from_address', 'postmark_from_name',
            'mailgun_domain', 'mailgun_secret', 'mailgun_endpoint',
            'mailgun_from_address', 'mailgun_from_name',
            'ses_key', 'ses_secret', 'ses_region', 'ses_from_address', 'ses_from_name',
            'email_footer_signature', 'email_footer_unsubscribe_url',
            'authentication_idle_time', 'max_login_attempts',
            'storage_driver',
            's3_key', 's3_secret', 's3_region', 's3_bucket', 's3_url',
            'r2_account_id', 'r2_access_key', 'r2_secret', 'r2_bucket', 'r2_url',
            'gcs_project_id', 'gcs_key_json', 'gcs_bucket', 'gcs_url',
            'ftp_host', 'ftp_port', 'ftp_username', 'ftp_password', 'ftp_root', 'ftp_passive',
            'sftp_host', 'sftp_port', 'sftp_username', 'sftp_password', 'sftp_private_key', 'sftp_root',
            'sms_driver',
            'twilio_sid', 'twilio_token', 'twilio_from',
            'vonage_key', 'vonage_secret', 'vonage_from',
            'sns_sms_key', 'sns_sms_secret', 'sns_sms_region', 'sns_sms_sender_id',
            'push_driver',
            'fcm_project_id', 'fcm_server_key',
            'apns_key_id', 'apns_team_id', 'apns_private_key', 'apns_bundle_id', 'apns_environment',
            'onesignal_app_id', 'onesignal_rest_api_key',
            'password_min_length', 'password_require_uppercase', 'password_require_digit',
            'password_require_symbol', 'password_expiry_days',
            'two_factor_auth', 'session_concurrency_limit',
            'app_name', 'support_email', 'support_url', 'logo_url', 'favicon_url',
        ];

        $rows = SystemSetting::whereIn('key', $keys)->pluck('value', 'key');
        $map = array_combine($keys, array_map(fn ($key) => $rows[$key] ?? null, $keys));

        return SystemSettingsData::from($map);
    }

    /**
     * Update system settings.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSettings(array $data): void
    {
        foreach ($data as $key => $value) {
            SystemSetting::set($key, $value);
        }
    }

    /**
     * Check for missing required settings and return their labels.
     */
    public function getMissingRequiredSettings(): MissingSystemSettingsData
    {
        $keys = array_keys(self::REQUIRED_SETTINGS);
        $existing = SystemSetting::whereIn('key', $keys)
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->pluck('value', 'key');

        $labels = [];
        foreach (self::REQUIRED_SETTINGS as $key => $label) {
            if (! isset($existing[$key])) {
                $labels[] = $label;
            }
        }

        return new MissingSystemSettingsData(labels: $labels);
    }
}
