<?php

namespace App\Storage\Tests;

use App\Models\Central\SystemSetting;
use App\Models\Central\TenantSetting;
use App\Storage\StorageResolver;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StorageResolverTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, string|null> $tenantSettings */
    private function makeResolver(array $tenantSettings = []): StorageResolver
    {
        foreach ($tenantSettings as $key => $value) {
            TenantSetting::create(['tenant_id' => 1, 'key' => $key, 'value' => $value]);
        }

        return $this->app->make(StorageResolver::class);
    }

    #[Test]
    public function for_tenant_returns_disk_when_tenant_driver_is_local(): void
    {
        $resolver = $this->makeResolver(['storage_driver' => 'local']);

        $this->assertInstanceOf(Filesystem::class, $resolver->forTenant(1));
    }

    #[Test]
    public function for_tenant_falls_back_to_system_when_tenant_driver_not_set(): void
    {
        SystemSetting::set('storage_driver', 'local');

        $resolver = $this->makeResolver([]);

        $this->assertInstanceOf(Filesystem::class, $resolver->forTenant(1));
    }

    #[Test]
    public function for_tenant_returns_env_fallback_when_no_driver_configured(): void
    {
        $resolver = $this->makeResolver([]);

        $this->assertInstanceOf(Filesystem::class, $resolver->forTenant(1));
    }

    #[Test]
    public function for_tenant_throws_when_s3_bucket_is_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/bucket/');

        $resolver = $this->makeResolver([
            'storage_driver' => 's3',
            's3_key' => 'key',
            's3_secret' => 'secret',
            's3_region' => 'ap-southeast-2',
            // s3_bucket intentionally missing
        ]);

        $resolver->forTenant(1);
    }

    #[Test]
    public function for_tenant_uses_system_credentials_when_tenant_has_driver_but_no_credentials(): void
    {
        SystemSetting::set('s3_key', 'system-key');
        SystemSetting::set('s3_secret', 'system-secret');
        SystemSetting::set('s3_region', 'ap-southeast-2');
        SystemSetting::set('s3_bucket', 'system-bucket');

        $fakeDisk = Mockery::mock(Filesystem::class);
        Storage::shouldReceive('build')
            ->once()
            ->withArgs(fn (array $config) => $config['key'] === 'system-key'
                && $config['secret'] === 'system-secret'
                && $config['bucket'] === 'system-bucket')
            ->andReturn($fakeDisk);

        $resolver = $this->makeResolver(['storage_driver' => 's3']);

        // Should NOT throw — system has the credentials
        $disk = $resolver->forTenant(1);
        $this->assertInstanceOf(Filesystem::class, $disk);
    }

    #[Test]
    public function for_system_returns_disk_when_system_driver_is_local(): void
    {
        SystemSetting::set('storage_driver', 'local');

        $resolver = $this->app->make(StorageResolver::class);

        $this->assertInstanceOf(Filesystem::class, $resolver->forSystem());
    }

    #[Test]
    public function for_system_returns_env_fallback_when_no_driver_configured(): void
    {
        $resolver = $this->app->make(StorageResolver::class);

        $this->assertInstanceOf(Filesystem::class, $resolver->forSystem());
    }

    #[Test]
    public function for_system_throws_when_s3_key_is_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/key/');

        SystemSetting::set('storage_driver', 's3');
        SystemSetting::set('s3_bucket', 'my-bucket');
        // s3_key and s3_secret missing

        $resolver = $this->app->make(StorageResolver::class);

        $resolver->forSystem();
    }

    #[Test]
    public function for_system_throws_for_unsupported_driver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unsupported storage driver/');

        SystemSetting::set('storage_driver', 'dropbox');

        $resolver = $this->app->make(StorageResolver::class);

        $resolver->forSystem();
    }
}
