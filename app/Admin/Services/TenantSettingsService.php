<?php

namespace App\Admin\Services;

use App\Admin\Data\TenantSettingsData;
use App\Models\Central\SystemSetting;
use App\Repositories\Central\TenantSettingRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TenantSettingsService
{
    private const KEYS = [
        'email_driver',
        'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
        'smtp_encryption', 'smtp_from_address', 'smtp_from_name',
        'postmark_token', 'postmark_from_address', 'postmark_from_name',
        'mailgun_domain', 'mailgun_secret', 'mailgun_endpoint',
        'mailgun_from_address', 'mailgun_from_name',
        'ses_key', 'ses_secret', 'ses_region', 'ses_from_address', 'ses_from_name',
        'storage_driver',
        's3_key', 's3_secret', 's3_region', 's3_bucket', 's3_url',
        'r2_account_id', 'r2_access_key', 'r2_secret', 'r2_bucket', 'r2_url',
        'report_pdf_header_text', 'report_pdf_footer_text', 'report_logo_path',
        'report_queue', 'report_timeout', 'report_connection',
        'app_name', 'support_email', 'support_url',
    ];

    public function __construct(
        private readonly TenantSettingRepository $repository,
    ) {}

    /**
     * Get the settings for a given tenant.
     *
     * @param  int  $tenantId  identifier of the tenant
     */
    public function getSettings(int $tenantId): TenantSettingsData
    {
        $tenant = $this->repository->allForTenant($tenantId);

        $t = fn (string $key): ?string => $tenant[$key] ?? null;

        $systemEmail = SystemSetting::get('email_driver');
        $systemStorage = SystemSetting::get('storage_driver');

        return new TenantSettingsData(
            emailDriver: $t('email_driver'),
            smtpHost: $t('smtp_host'),
            smtpPort: $t('smtp_port'),
            smtpUsername: $t('smtp_username'),
            smtpPassword: $t('smtp_password'),
            smtpEncryption: $t('smtp_encryption'),
            smtpFromAddress: $t('smtp_from_address'),
            smtpFromName: $t('smtp_from_name'),
            postmarkToken: $t('postmark_token'),
            postmarkFromAddress: $t('postmark_from_address'),
            postmarkFromName: $t('postmark_from_name'),
            mailgunDomain: $t('mailgun_domain'),
            mailgunSecret: $t('mailgun_secret'),
            mailgunEndpoint: $t('mailgun_endpoint'),
            mailgunFromAddress: $t('mailgun_from_address'),
            mailgunFromName: $t('mailgun_from_name'),
            sesKey: $t('ses_key'),
            sesSecret: $t('ses_secret'),
            sesRegion: $t('ses_region'),
            sesFromAddress: $t('ses_from_address'),
            sesFromName: $t('ses_from_name'),
            storageDriver: $t('storage_driver'),
            s3Key: $t('s3_key'),
            s3Secret: $t('s3_secret'),
            s3Region: $t('s3_region'),
            s3Bucket: $t('s3_bucket'),
            s3Url: $t('s3_url'),
            r2AccountId: $t('r2_account_id'),
            r2AccessKey: $t('r2_access_key'),
            r2Secret: $t('r2_secret'),
            r2Bucket: $t('r2_bucket'),
            r2Url: $t('r2_url'),
            reportPdfHeaderText: $t('report_pdf_header_text'),
            reportPdfFooterText: $t('report_pdf_footer_text'),
            reportLogoPath: $t('report_logo_path'),
            reportQueue: $t('report_queue'),
            reportTimeout: $t('report_timeout'),
            reportConnection: $t('report_connection'),
            appName: $t('app_name'),
            supportEmail: $t('support_email'),
            supportUrl: $t('support_url'),
            effectiveEmailDriver: $t('email_driver') ?? $systemEmail,
            effectiveStorageDriver: $t('storage_driver') ?? $systemStorage,
        );
    }

    /**
     * Update the settings for a given tenant.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSettings(int $tenantId, array $data): void
    {
        $allowed = array_filter(
            $data,
            fn (string $key) => in_array($key, self::KEYS, strict: true),
            ARRAY_FILTER_USE_KEY
        );

        $this->repository->setMany($tenantId, $allowed);
    }

    /**
     * Upload a logo for a given tenant.
     *
     * @param  int  $tenantId  identifier of the tenant
     * @param  UploadedFile  $file  the uploaded logo file
     * @return string the path to the uploaded logo
     */
    public function uploadLogo(int $tenantId, UploadedFile $file): string
    {
        $path = $file->store("tenant-logos/{$tenantId}", 'public');

        $this->repository->set($tenantId, 'report_logo_path', $path);

        return $path;
    }

    /**
     * Delete the logo for a given tenant.
     *
     * @param  int  $tenantId  identifier of the tenant
     */
    public function deleteLogo(int $tenantId): void
    {
        $existing = $this->repository->get($tenantId, 'report_logo_path');

        if ($existing) {
            Storage::disk('public')->delete($existing);
        }

        $this->repository->delete($tenantId, 'report_logo_path');
    }

    /**
     * Resolve the effective mail configuration for a tenant at runtime.
     *
     * @return array<string, mixed>
     */
    public function resolveMailConfig(int $tenantId): array
    {
        $tenant = $this->repository->allForTenant($tenantId);
        $t = fn (string $key): ?string => $tenant[$key] ?? null;

        $driver = $t('email_driver') ?? SystemSetting::get('email_driver', 'smtp');

        return match ($driver) {
            'smtp' => [
                'transport' => 'smtp',
                'host' => $t('smtp_host') ?? SystemSetting::get('smtp_host', 'localhost'),
                'port' => (int) ($t('smtp_port') ?? SystemSetting::get('smtp_port', 587)),
                'encryption' => $t('smtp_encryption') ?? SystemSetting::get('smtp_encryption', 'tls'),
                'username' => $t('smtp_username') ?? SystemSetting::get('smtp_username'),
                'password' => $t('smtp_password') ?? SystemSetting::get('smtp_password'),
            ],
            'postmark' => [
                'transport' => 'postmark',
                'token' => $t('postmark_token') ?? SystemSetting::get('postmark_token'),
            ],
            'mailgun' => [
                'transport' => 'mailgun',
                'secret' => $t('mailgun_secret') ?? SystemSetting::get('mailgun_secret'),
                'domain' => $t('mailgun_domain') ?? SystemSetting::get('mailgun_domain'),
                'endpoint' => $t('mailgun_endpoint') ?? SystemSetting::get('mailgun_endpoint', 'api.mailgun.net'),
            ],
            'ses' => [
                'transport' => 'ses',
                'key' => $t('ses_key') ?? SystemSetting::get('ses_key'),
                'secret' => $t('ses_secret') ?? SystemSetting::get('ses_secret'),
                'region' => $t('ses_region') ?? SystemSetting::get('ses_region', 'us-east-1'),
            ],
            default => ['transport' => $driver],
        };
    }

    /**
     * Resolve the effective S3 disk configuration for a tenant at runtime.
     *
     * @return array<string, mixed>
     */
    public function resolveS3Config(int $tenantId): array
    {
        $tenant = $this->repository->allForTenant($tenantId);
        $t = fn (string $key): ?string => $tenant[$key] ?? null;

        return [
            'driver' => 's3',
            'key' => $t('s3_key') ?? SystemSetting::get('s3_key'),
            'secret' => $t('s3_secret') ?? SystemSetting::get('s3_secret'),
            'region' => $t('s3_region') ?? SystemSetting::get('s3_region', 'us-east-1'),
            'bucket' => $t('s3_bucket') ?? SystemSetting::get('s3_bucket'),
            'url' => $t('s3_url') ?? SystemSetting::get('s3_url'),
        ];
    }
}
