<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email_driver' => ['nullable', 'string', 'in:smtp,postmark,mailgun,ses'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['nullable', 'string', 'in:tls,ssl,starttls'],
            'smtp_from_address' => ['nullable', 'email', 'max:255'],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
            'postmark_token' => ['nullable', 'string', 'max:255'],
            'postmark_from_address' => ['nullable', 'email', 'max:255'],
            'postmark_from_name' => ['nullable', 'string', 'max:255'],
            'mailgun_domain' => ['nullable', 'string', 'max:255'],
            'mailgun_secret' => ['nullable', 'string', 'max:255'],
            'mailgun_endpoint' => ['nullable', 'string', 'in:api.mailgun.net,api.eu.mailgun.net'],
            'mailgun_from_address' => ['nullable', 'email', 'max:255'],
            'mailgun_from_name' => ['nullable', 'string', 'max:255'],
            'email_footer_signature' => ['nullable', 'string', 'max:2000'],
            'email_footer_unsubscribe_url' => ['nullable', 'url', 'max:255'],
            'ses_key' => ['nullable', 'string', 'max:255'],
            'ses_secret' => ['nullable', 'string', 'max:255'],
            'ses_region' => ['nullable', 'string', 'max:255'],
            'ses_from_address' => ['nullable', 'email', 'max:255'],
            'ses_from_name' => ['nullable', 'string', 'max:255'],
            'authentication_idle_time' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'max_login_attempts' => ['nullable', 'integer', 'min:1', 'max:100'],
            'storage_driver' => ['nullable', 'string', 'in:local,s3,r2,gcs,ftp,sftp'],
            's3_key' => ['nullable', 'string', 'max:255'],
            's3_secret' => ['nullable', 'string', 'max:255'],
            's3_region' => ['nullable', 'string', 'max:255'],
            's3_bucket' => ['nullable', 'string', 'max:255'],
            's3_url' => ['nullable', 'url', 'max:255'],
            'r2_account_id' => ['nullable', 'string', 'max:255'],
            'r2_access_key' => ['nullable', 'string', 'max:255'],
            'r2_secret' => ['nullable', 'string', 'max:255'],
            'r2_bucket' => ['nullable', 'string', 'max:255'],
            'r2_url' => ['nullable', 'url', 'max:255'],
            'gcs_project_id' => ['nullable', 'string', 'max:255'],
            'gcs_key_json' => ['nullable', 'string'],
            'gcs_bucket' => ['nullable', 'string', 'max:255'],
            'gcs_url' => ['nullable', 'url', 'max:255'],
            'ftp_host' => ['nullable', 'string', 'max:255'],
            'ftp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ftp_username' => ['nullable', 'string', 'max:255'],
            'ftp_password' => ['nullable', 'string', 'max:255'],
            'ftp_root' => ['nullable', 'string', 'max:255'],
            'ftp_passive' => ['nullable', 'boolean'],
            'sftp_host' => ['nullable', 'string', 'max:255'],
            'sftp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'sftp_username' => ['nullable', 'string', 'max:255'],
            'sftp_password' => ['nullable', 'string', 'max:255'],
            'sftp_private_key' => ['nullable', 'string'],
            'sftp_root' => ['nullable', 'string', 'max:255'],
            'sms_driver' => ['nullable', 'string', 'in:twilio,vonage,sns'],
            'twilio_sid' => ['nullable', 'string', 'max:255'],
            'twilio_token' => ['nullable', 'string', 'max:255'],
            'twilio_from' => ['nullable', 'string', 'max:20'],
            'vonage_key' => ['nullable', 'string', 'max:255'],
            'vonage_secret' => ['nullable', 'string', 'max:255'],
            'vonage_from' => ['nullable', 'string', 'max:20'],
            'sns_sms_key' => ['nullable', 'string', 'max:255'],
            'sns_sms_secret' => ['nullable', 'string', 'max:255'],
            'sns_sms_region' => ['nullable', 'string', 'max:255'],
            'sns_sms_sender_id' => ['nullable', 'string', 'max:11'],
            'push_driver' => ['nullable', 'string', 'in:fcm,apns,onesignal'],
            'fcm_project_id' => ['nullable', 'string', 'max:255'],
            'fcm_server_key' => ['nullable', 'string', 'max:255'],
            'apns_key_id' => ['nullable', 'string', 'max:255'],
            'apns_team_id' => ['nullable', 'string', 'max:255'],
            'apns_private_key' => ['nullable', 'string'],
            'apns_bundle_id' => ['nullable', 'string', 'max:255'],
            'apns_environment' => ['nullable', 'string', 'in:sandbox,production'],
            'onesignal_app_id' => ['nullable', 'string', 'max:255'],
            'onesignal_rest_api_key' => ['nullable', 'string', 'max:255'],
            'password_min_length' => ['nullable', 'integer', 'min:6', 'max:128'],
            'password_require_uppercase' => ['nullable', 'boolean'],
            'password_require_digit' => ['nullable', 'boolean'],
            'password_require_symbol' => ['nullable', 'boolean'],
            'password_expiry_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'two_factor_auth' => ['nullable', 'string', 'in:off,optional,required'],
            'session_concurrency_limit' => ['nullable', 'integer', 'min:0', 'max:100'],
            'app_name' => ['nullable', 'string', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_url' => ['nullable', 'url', 'max:255'],
            'logo_url' => ['nullable', 'url', 'max:255'],
            'favicon_url' => ['nullable', 'url', 'max:255'],
            'upload_allowed_types' => ['nullable', 'string', 'max:255'],
            'upload_max_size_pdf' => ['nullable', 'integer', 'min:1', 'max:100'],
            'upload_max_size_doc' => ['nullable', 'integer', 'min:1', 'max:100'],
            'upload_max_size_text' => ['nullable', 'integer', 'min:1', 'max:100'],
            'upload_max_size_excel' => ['nullable', 'integer', 'min:1', 'max:100'],
            'upload_max_size_image' => ['nullable', 'integer', 'min:1', 'max:100'],
            'upload_max_size_csv' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
