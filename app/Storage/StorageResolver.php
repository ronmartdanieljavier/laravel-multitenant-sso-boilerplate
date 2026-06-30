<?php

namespace App\Storage;

use App\Models\Central\SystemSetting;
use App\Repositories\Central\TenantSettingRepository;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class StorageResolver
{
    public function __construct(
        private readonly TenantSettingRepository $repository,
    ) {}

    public function forTenant(int $tenantId): FilesystemAdapter
    {
        $tenant = $this->repository->allForTenant($tenantId)->all();
        $t = fn (string $key): ?string => $tenant[$key] ?? null;

        $driver = $t('storage_driver') ?? SystemSetting::get('storage_driver');

        if ($driver === null) {
            return $this->envFallback();
        }

        return $this->buildDisk($driver, $t);
    }

    public function forSystem(): FilesystemAdapter
    {
        $driver = SystemSetting::get('storage_driver');

        if ($driver === null) {
            return $this->envFallback();
        }

        return $this->buildDisk($driver, null);
    }

    private function buildDisk(string $driver, ?callable $tenantGet): FilesystemAdapter
    {
        $get = fn (string $key, ?string $envKey = null): ?string => ($tenantGet !== null ? $tenantGet($key) : null)
            ?? SystemSetting::get($key)
            ?? ($envKey !== null ? Env::get($envKey) : null);

        $config = match ($driver) {
            's3' => [
                'driver' => 's3',
                'key' => $get('s3_key', 'AWS_ACCESS_KEY_ID'),
                'secret' => $get('s3_secret', 'AWS_SECRET_ACCESS_KEY'),
                'region' => $get('s3_region', 'AWS_DEFAULT_REGION') ?? 'us-east-1',
                'bucket' => $get('s3_bucket', 'AWS_BUCKET'),
                'url' => $get('s3_url', 'AWS_URL'),
                'visibility' => 'private',
                'throw' => false,
                'report' => false,
            ],
            'r2' => [
                'driver' => 's3',
                'key' => $get('r2_access_key', 'R2_ACCESS_KEY'),
                'secret' => $get('r2_secret', 'R2_SECRET'),
                'region' => 'auto',
                'bucket' => $get('r2_bucket', 'R2_BUCKET'),
                'url' => $get('r2_url', 'R2_URL'),
                'endpoint' => 'https://'.($get('r2_account_id', 'R2_ACCOUNT_ID') ?? '').'.r2.cloudflarestorage.com',
                'use_path_style_endpoint' => true,
                'visibility' => 'private',
                'throw' => false,
                'report' => false,
                'r2_account_id' => $get('r2_account_id', 'R2_ACCOUNT_ID'),
            ],
            'gcs' => [
                'driver' => 'gcs',
                'project_id' => $get('gcs_project_id', 'GCS_PROJECT_ID'),
                'key_json' => $get('gcs_key_json', 'GCS_KEY_JSON'),
                'bucket' => $get('gcs_bucket', 'GCS_BUCKET'),
                'url' => $get('gcs_url', 'GCS_URL'),
                'throw' => false,
                'report' => false,
            ],
            'ftp' => [
                'driver' => 'ftp',
                'host' => $get('ftp_host', 'FTP_HOST'),
                'port' => (int) ($get('ftp_port', 'FTP_PORT') ?? 21),
                'username' => $get('ftp_username', 'FTP_USERNAME'),
                'password' => $get('ftp_password', 'FTP_PASSWORD'),
                'root' => $get('ftp_root', 'FTP_ROOT') ?? '/',
                'passive' => filter_var($get('ftp_passive', 'FTP_PASSIVE') ?? 'true', FILTER_VALIDATE_BOOLEAN),
                'throw' => false,
                'report' => false,
            ],
            'sftp' => [
                'driver' => 'sftp',
                'host' => $get('sftp_host', 'SFTP_HOST'),
                'port' => (int) ($get('sftp_port', 'SFTP_PORT') ?? 22),
                'username' => $get('sftp_username', 'SFTP_USERNAME'),
                'password' => $get('sftp_password', 'SFTP_PASSWORD'),
                'privateKey' => $get('sftp_private_key', 'SFTP_PRIVATE_KEY'),
                'root' => $get('sftp_root', 'SFTP_ROOT') ?? '/',
                'throw' => false,
                'report' => false,
            ],
            'local' => [
                'driver' => 'local',
                'root' => storage_path('app'),
                'throw' => false,
                'report' => false,
            ],
            default => throw new InvalidArgumentException("Unsupported storage driver: [{$driver}]."),
        };

        $this->validateConfig($driver, $config);

        $disk = Storage::build($config);

        if (! $disk instanceof FilesystemAdapter) {
            throw new \RuntimeException('Storage::build() did not return a FilesystemAdapter.');
        }

        return $disk;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function validateConfig(string $driver, array $config): void
    {
        $required = match ($driver) {
            's3' => ['key', 'secret', 'bucket'],
            'r2' => ['key', 'secret', 'bucket', 'r2_account_id'],
            'gcs' => ['project_id', 'bucket'],
            'ftp' => ['host', 'username'],
            'sftp' => ['host', 'username'],
            default => [],
        };

        foreach ($required as $field) {
            if (empty($config[$field])) {
                throw new InvalidArgumentException(
                    "Missing required credential '{$field}' for storage driver '{$driver}'."
                );
            }
        }
    }

    private function envFallback(): FilesystemAdapter
    {
        return Storage::disk(config('filesystems.default'));
    }
}
