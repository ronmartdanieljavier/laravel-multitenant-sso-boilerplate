<?php

namespace App\Admin\Data;

use Spatie\LaravelData\Data;

class TenantSettingsData extends Data
{
    public function __construct(
        // Email
        public ?string $emailDriver,
        public ?string $smtpHost,
        public ?string $smtpPort,
        public ?string $smtpUsername,
        public ?string $smtpPassword,
        public ?string $smtpEncryption,
        public ?string $smtpFromAddress,
        public ?string $smtpFromName,
        public ?string $postmarkToken,
        public ?string $postmarkFromAddress,
        public ?string $postmarkFromName,
        public ?string $mailgunDomain,
        public ?string $mailgunSecret,
        public ?string $mailgunEndpoint,
        public ?string $mailgunFromAddress,
        public ?string $mailgunFromName,
        public ?string $sesKey,
        public ?string $sesSecret,
        public ?string $sesRegion,
        public ?string $sesFromAddress,
        public ?string $sesFromName,
        // Storage
        public ?string $storageDriver,
        public ?string $s3Key,
        public ?string $s3Secret,
        public ?string $s3Region,
        public ?string $s3Bucket,
        public ?string $s3Url,
        public ?string $r2AccountId,
        public ?string $r2AccessKey,
        public ?string $r2Secret,
        public ?string $r2Bucket,
        public ?string $r2Url,
        // Report PDF
        public ?string $reportPdfHeaderText,
        public ?string $reportPdfFooterText,
        public ?string $reportLogoPath,
        // Report server
        public ?string $reportQueue,
        public ?string $reportTimeout,
        public ?string $reportConnection,
        // Branding overrides
        public ?string $appName,
        public ?string $supportEmail,
        public ?string $supportUrl,
        // Upload overrides
        public ?string $uploadAllowedTypes,
        public ?string $uploadMaxSizePdf,
        public ?string $uploadMaxSizeDoc,
        public ?string $uploadMaxSizeText,
        public ?string $uploadMaxSizeExcel,
        public ?string $uploadMaxSizeImage,
        public ?string $uploadMaxSizeCsv,
        // Effective values (tenant override ?? system default)
        public ?string $effectiveEmailDriver,
        public ?string $effectiveStorageDriver,
        public ?string $effectiveUploadAllowedTypes,
        public ?string $effectiveUploadMaxSizePdf,
        public ?string $effectiveUploadMaxSizeDoc,
        public ?string $effectiveUploadMaxSizeText,
        public ?string $effectiveUploadMaxSizeExcel,
        public ?string $effectiveUploadMaxSizeImage,
        public ?string $effectiveUploadMaxSizeCsv,
    ) {}
}
