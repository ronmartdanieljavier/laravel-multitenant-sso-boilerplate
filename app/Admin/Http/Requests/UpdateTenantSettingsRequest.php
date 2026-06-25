<?php

namespace App\Admin\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantSettingsRequest extends FormRequest
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
            'ses_key' => ['nullable', 'string', 'max:255'],
            'ses_secret' => ['nullable', 'string', 'max:255'],
            'ses_region' => ['nullable', 'string', 'max:255'],
            'ses_from_address' => ['nullable', 'email', 'max:255'],
            'ses_from_name' => ['nullable', 'string', 'max:255'],
            'storage_driver' => ['nullable', 'string', 'in:s3,r2'],
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
            'report_pdf_header_text' => ['nullable', 'string', 'max:5000'],
            'report_pdf_footer_text' => ['nullable', 'string', 'max:5000'],
            'report_queue' => ['nullable', 'string', 'max:255'],
            'report_timeout' => ['nullable', 'integer', 'min:10', 'max:3600'],
            'report_connection' => ['nullable', 'string', 'max:255'],
            'app_name' => ['nullable', 'string', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_url' => ['nullable', 'url', 'max:255'],
        ];
    }
}
