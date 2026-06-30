# Storage Resolver Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Introduce `StorageResolver` — a single service that resolves the correct `Filesystem` disk at runtime using a priority chain (Tenant → System → ENV for tenant files; System → ENV for admin/system files) — and wire it into every storage call site in the application.

**Architecture:** A new `App\Storage\StorageResolver` class owns all resolution logic and is injected into existing services/controllers. It replaces the inline `resolveDisk()` logic in `TenantSettingsService` and all hardcoded `Storage::disk('reports')` / `Storage::disk('s3')` / `Storage::disk('public')` calls across the codebase.

**Tech Stack:** Laravel 13, PHP 8.5, Spatie Laravel Data, PHPUnit 12, Laravel Storage facades (`Storage::build()`, `Storage::disk()`).

## Global Constraints

- PHP 8.5 — use constructor property promotion, match expressions, named arguments.
- All DTOs extend `Spatie\LaravelData\Data`; DTO properties are camelCase.
- Architecture: Controller → Service → Repository → Model. No Eloquent outside repositories. Services return DTOs, never models.
- Every change must be tested. Run `php artisan test --compact` with a filter before finalising each task.
- Run `vendor/bin/pint --dirty --format agent` after every PHP file change.
- Frequent commits — one per task.

---

## File Map

**New files:**
- `app/Storage/StorageResolver.php` — the resolver service
- `app/Storage/Tests/StorageResolverTest.php` — unit + integration tests

**Modified files:**
- `app/Admin/Services/TenantSettingsService.php` — delegate `resolveDisk()`, fix `uploadLogo()`/`deleteLogo()`, remove `resolveS3Config()`
- `app/Reports/Services/ReportFileService.php` — inject resolver, replace `Storage::disk('reports')`
- `app/Reports/Http/Controllers/ReportController.php` — inject resolver, replace `Storage::disk('reports')`
- `app/Documents/Services/DocumentService.php` — replace `Storage::disk('reports')` in `createFromReport()` and `downloadZipResponse()`
- `app/Reports/Services/ReportDeliveryService.php` — rename `uploadToS3()` → `uploadToStorage()`, use resolver
- `app/Reports/Jobs/GenerateReportJob.php` — update `uploadToS3` call sites to `uploadToStorage`
- `app/Profile/Http/Controllers/ProfileController.php` — use `forSystem()`
- `app/Profile/Http/Controllers/ProfileApiController.php` — use `forSystem()`
- `app/Models/Central/User.php` — use `app(StorageResolver::class)->forSystem()` in accessor
- `app/Http/Resources/UserResource.php` — use resolver for profile picture URL
- `app/Http/Middleware/HandleInertiaRequests.php` — inject resolver, use `forSystem()`

---

## Task 1: Create StorageResolver

**Files:**
- Create: `app/Storage/StorageResolver.php`
- Create: `app/Storage/Tests/StorageResolverTest.php`

**Interfaces:**
- Produces:
  - `StorageResolver::forTenant(int $tenantId): Filesystem`
  - `StorageResolver::forSystem(): Filesystem`

- [ ] **Step 1: Create the StorageResolver class**

Create `app/Storage/StorageResolver.php`:

```php
<?php

namespace App\Storage;

use App\Models\Central\SystemSetting;
use App\Repositories\Central\TenantSettingRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class StorageResolver
{
    public function __construct(
        private readonly TenantSettingRepository $repository,
    ) {}

    public function forTenant(int $tenantId): Filesystem
    {
        $tenant = $this->repository->allForTenant($tenantId)->all();
        $t = fn (string $key): ?string => $tenant[$key] ?? null;

        $driver = $t('storage_driver') ?? SystemSetting::get('storage_driver');

        if ($driver === null) {
            return $this->envFallback();
        }

        return $this->buildDisk($driver, $t);
    }

    public function forSystem(): Filesystem
    {
        $driver = SystemSetting::get('storage_driver');

        if ($driver === null) {
            return $this->envFallback();
        }

        return $this->buildDisk($driver, null);
    }

    private function buildDisk(string $driver, ?callable $tenantGet): Filesystem
    {
        $get = fn (string $key, ?string $envKey = null): ?string =>
            ($tenantGet !== null ? $tenantGet($key) : null)
            ?? SystemSetting::get($key)
            ?? ($envKey !== null ? env($envKey) : null);

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

        return Storage::build($config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateConfig(string $driver, array $config): void
    {
        $required = match ($driver) {
            's3' => ['key', 'secret', 'bucket'],
            'r2' => ['key', 'secret', 'bucket'],
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

    private function envFallback(): Filesystem
    {
        return Storage::disk(config('filesystems.default'));
    }
}
```

- [ ] **Step 2: Write the tests**

Create `app/Storage/Tests/StorageResolverTest.php`:

```php
<?php

namespace App\Storage\Tests;

use App\Models\Central\SystemSetting;
use App\Repositories\Central\TenantSettingRepository;
use App\Storage\StorageResolver;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $repo = Mockery::mock(TenantSettingRepository::class);
        $repo->shouldReceive('allForTenant')->andReturn(collect($tenantSettings));

        return new StorageResolver($repo);
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
        $this->expectExceptionMessageMatches("/bucket/");

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

        // Tenant sets driver but no credentials — falls back to system credentials
        $this->expectException(InvalidArgumentException::class); // would only throw if credentials missing

        $resolver = $this->makeResolver(['storage_driver' => 's3']);

        // Should NOT throw — system has the credentials
        $disk = $resolver->forTenant(1);
        $this->assertInstanceOf(Filesystem::class, $disk);
    }

    #[Test]
    public function for_system_returns_disk_when_system_driver_is_local(): void
    {
        SystemSetting::set('storage_driver', 'local');

        $repo = Mockery::mock(TenantSettingRepository::class);
        $resolver = new StorageResolver($repo);

        $this->assertInstanceOf(Filesystem::class, $resolver->forSystem());
    }

    #[Test]
    public function for_system_returns_env_fallback_when_no_driver_configured(): void
    {
        $repo = Mockery::mock(TenantSettingRepository::class);
        $resolver = new StorageResolver($repo);

        $this->assertInstanceOf(Filesystem::class, $resolver->forSystem());
    }

    #[Test]
    public function for_system_throws_when_s3_key_is_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/key/");

        SystemSetting::set('storage_driver', 's3');
        SystemSetting::set('s3_bucket', 'my-bucket');
        // s3_key and s3_secret missing

        $repo = Mockery::mock(TenantSettingRepository::class);
        $resolver = new StorageResolver($repo);

        $resolver->forSystem();
    }

    #[Test]
    public function for_system_throws_for_unsupported_driver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/Unsupported storage driver/");

        SystemSetting::set('storage_driver', 'dropbox');

        $repo = Mockery::mock(TenantSettingRepository::class);
        $resolver = new StorageResolver($repo);

        $resolver->forSystem();
    }
}
```

- [ ] **Step 3: Fix the over-asserting test**

The test `for_tenant_uses_system_credentials_when_tenant_has_driver_but_no_credentials` has a wrong `expectException` call — remove it. The corrected version:

```php
#[Test]
public function for_tenant_uses_system_credentials_when_tenant_has_driver_but_no_credentials(): void
{
    SystemSetting::set('s3_key', 'system-key');
    SystemSetting::set('s3_secret', 'system-secret');
    SystemSetting::set('s3_region', 'ap-southeast-2');
    SystemSetting::set('s3_bucket', 'system-bucket');

    $resolver = $this->makeResolver(['storage_driver' => 's3']);

    $this->assertInstanceOf(Filesystem::class, $resolver->forTenant(1));
}
```

- [ ] **Step 4: Run the tests**

```bash
php artisan test --compact tests/Feature/../app/Storage/Tests/StorageResolverTest.php
# or if that path doesn't work:
php artisan test --compact --filter=StorageResolverTest
```

Expected: all tests pass.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Storage/StorageResolver.php app/Storage/Tests/StorageResolverTest.php
git commit -m "feat: add StorageResolver with tenant/system/env priority chain"
```

---

## Task 2: Refactor TenantSettingsService

**Files:**
- Modify: `app/Admin/Services/TenantSettingsService.php`

**Interfaces:**
- Consumes: `StorageResolver::forTenant(int $tenantId): Filesystem` (from Task 1)
- Produces: `TenantSettingsService::resolveDisk(int $tenantId): Filesystem` — same signature, now delegates to resolver

- [ ] **Step 1: Inject StorageResolver and delegate resolveDisk**

In `app/Admin/Services/TenantSettingsService.php`:

1. Add the import at the top:
```php
use App\Storage\StorageResolver;
```

2. Update the constructor to inject `StorageResolver`:
```php
public function __construct(
    private readonly TenantSettingRepository $repository,
    private readonly StorageResolver $resolver,
) {}
```

3. Replace the entire `resolveDisk()` method body with a one-line delegation:
```php
public function resolveDisk(int $tenantId): Filesystem
{
    return $this->resolver->forTenant($tenantId);
}
```

4. Remove the entire `resolveS3Config()` method (it has no external callers).

- [ ] **Step 2: Fix uploadLogo and deleteLogo**

Replace `uploadLogo()`:
```php
public function uploadLogo(int $tenantId, UploadedFile $file): string
{
    $disk = $this->resolver->forTenant($tenantId);
    $path = $disk->putFileAs("tenant-logos/{$tenantId}", $file, $file->hashName());

    $this->repository->set($tenantId, 'report_logo_path', $path);

    return $path;
}
```

Replace `deleteLogo()`:
```php
public function deleteLogo(int $tenantId): void
{
    $existing = $this->repository->get($tenantId, 'report_logo_path');

    if ($existing) {
        $this->resolver->forTenant($tenantId)->delete($existing);
    }

    $this->repository->delete($tenantId, 'report_logo_path');
}
```

Also remove `use Illuminate\Support\Facades\Storage;` from the imports if it's no longer used after these changes (check the file for any remaining `Storage::` references first).

- [ ] **Step 3: Run affected tests**

```bash
php artisan test --compact --filter=TenantSettingsServiceTest
php artisan test --compact --filter=TenantSettingsWebTest
php artisan test --compact --filter=TenantSettingsApiTest
```

Expected: all pass.

- [ ] **Step 4: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Admin/Services/TenantSettingsService.php
git commit -m "refactor: delegate TenantSettingsService disk resolution to StorageResolver"
```

---

## Task 3: Update ReportFileService

**Files:**
- Modify: `app/Reports/Services/ReportFileService.php`

**Interfaces:**
- Consumes: `StorageResolver::forTenant(int $tenantId): Filesystem`, `StorageResolver::forSystem(): Filesystem` (Task 1)

- [ ] **Step 1: Inject StorageResolver and add disk helper**

In `app/Reports/Services/ReportFileService.php`:

1. Add imports:
```php
use App\Storage\StorageResolver;
```

2. Add constructor:
```php
public function __construct(
    private readonly StorageResolver $resolver,
) {}
```

3. Add a private helper (place after constructor):
```php
private function diskFor(Report $report): \Illuminate\Contracts\Filesystem\Filesystem
{
    return $report->tenant_id !== null
        ? $this->resolver->forTenant($report->tenant_id)
        : $this->resolver->forSystem();
}
```

- [ ] **Step 2: Replace all Storage::disk('reports') calls**

Replace `storeFile()`:
```php
public function storeFile(string $content, string $filename, Report $report): string
{
    $path = self::prefix($report).'/'.$filename;

    if ($this->diskFor($report)->put($path, $content) === false) {
        throw new RuntimeException("Failed to write report file at {$path}.");
    }

    return $path;
}
```

Replace `absolutePath()`:
```php
public function absolutePath(string $storagePath, Report $report): string
{
    return $this->diskFor($report)->path($storagePath);
}
```

Replace `exists()`:
```php
public function exists(string $storagePath, Report $report): bool
{
    return $this->diskFor($report)->exists($storagePath);
}
```

Note: `absolutePath()` and `exists()` now require a `Report` parameter. Check callers of these methods and update them to pass the report. If no callers exist for `absolutePath()`, keep the new signature anyway for consistency.

- [ ] **Step 3: Check callers of absolutePath and exists**

```bash
grep -rn "absolutePath\|->exists" app/Reports --include="*.php"
```

Update any caller to pass the `Report` instance. If `absolutePath` has no callers outside `ReportFileService`, no changes needed there.

- [ ] **Step 4: Remove the unused Storage import**

Remove `use Illuminate\Support\Facades\Storage;` from the top of the file.

- [ ] **Step 5: Run affected tests**

```bash
php artisan test --compact --filter=ReportFileService
php artisan test --compact --filter=PdfReportGeneratorTest
```

Expected: all pass.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Reports/Services/ReportFileService.php
git commit -m "refactor: route ReportFileService through StorageResolver"
```

---

## Task 4: Update ReportController

**Files:**
- Modify: `app/Reports/Http/Controllers/ReportController.php`

**Interfaces:**
- Consumes: `StorageResolver::forTenant(int $tenantId): Filesystem`, `StorageResolver::forSystem(): Filesystem` (Task 1)

- [ ] **Step 1: Inject StorageResolver**

In `app/Reports/Http/Controllers/ReportController.php`:

1. Add import:
```php
use App\Storage\StorageResolver;
```

2. Add constructor (or add to existing):
```php
public function __construct(
    private readonly StorageResolver $resolver,
) {}
```

- [ ] **Step 2: Replace Storage::disk('reports') in download() and destroy()**

Add a private helper:
```php
private function diskFor(Report $report): \Illuminate\Contracts\Filesystem\Filesystem
{
    return $report->tenant_id !== null
        ? $this->resolver->forTenant($report->tenant_id)
        : $this->resolver->forSystem();
}
```

Replace `download()` body:
```php
public function download(Request $request, Report $report): StreamedResponse
{
    Gate::authorize('download', $report);

    abort_unless(
        $report->status === ReportStatus::Success && $report->file_path !== null,
        Response::HTTP_UNPROCESSABLE_ENTITY,
        'Report is not ready for download.'
    );

    $storage = $this->diskFor($report);

    abort_unless($storage->exists($report->file_path), Response::HTTP_NOT_FOUND, 'Report file not found.');

    return $storage->download($report->file_path);
}
```

Replace `destroy()` body:
```php
public function destroy(Request $request, Report $report): JsonResponse
{
    Gate::authorize('delete', $report);

    if ($report->file_path !== null) {
        $this->diskFor($report)->delete($report->file_path);
    }

    $report->delete();

    return response()->json(null, Response::HTTP_NO_CONTENT);
}
```

- [ ] **Step 3: Remove unused Storage import**

Remove `use Illuminate\Support\Facades\Storage;` if no other `Storage::` calls remain.

- [ ] **Step 4: Run affected tests**

```bash
php artisan test --compact --filter=ReportWebTest
php artisan test --compact --filter=ReportApiTest
```

Expected: all pass.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Reports/Http/Controllers/ReportController.php
git commit -m "refactor: route ReportController storage through StorageResolver"
```

---

## Task 5: Update DocumentService

**Files:**
- Modify: `app/Documents/Services/DocumentService.php`

**Interfaces:**
- Consumes: `StorageResolver::forTenant(int $tenantId): Filesystem`, `StorageResolver::forSystem(): Filesystem` (Task 1)

- [ ] **Step 1: Inject StorageResolver and remove TenantSettingsService dependency**

In `app/Documents/Services/DocumentService.php`:

1. Add import:
```php
use App\Storage\StorageResolver;
```

2. Replace the constructor — swap `TenantSettingsService` for `StorageResolver` (the service was only used for `resolveDisk`):
```php
public function __construct(
    protected DocumentRepository $documentRepository,
    protected StorageResolver $resolver,
) {}
```

3. Replace all calls to `$this->tenantSettingsService->resolveDisk($tenantId)` with `$this->resolver->forTenant($tenantId)`.

- [ ] **Step 2: Fix createFromReport()**

The method uses `Storage::disk('reports')` for `size()`. Add a helper and fix it:

```php
private function diskForReport(Report $report): \Illuminate\Contracts\Filesystem\Filesystem
{
    return $report->tenant_id !== null
        ? $this->resolver->forTenant($report->tenant_id)
        : $this->resolver->forSystem();
}
```

Replace the `createFromReport()` disk call:
```php
$fileSize = $this->diskForReport($report)->size((string) $report->file_path);
```

- [ ] **Step 3: Fix downloadResponse() for Report source**

Replace:
```php
if ($dto->source === DocumentSource::Report) {
    return Storage::disk('reports')->download($dto->filePath, $dto->fileName);
}
```

With (the tenant context is already available as a parameter):
```php
if ($dto->source === DocumentSource::Report) {
    return $this->resolver->forTenant($tenantId)->download($dto->filePath, $dto->fileName);
}
```

- [ ] **Step 4: Fix downloadZipResponse()**

Replace the mixed disk resolution inside the loop:
```php
foreach ($documents as $doc) {
    $disk = $this->resolver->forTenant($tenantId);

    if (! $disk->exists($doc->filePath)) {
        continue;
    }

    $zip->addFromString($doc->fileName, $disk->get($doc->filePath));
}
```

- [ ] **Step 5: Remove unused imports**

Remove:
```php
use App\Admin\Services\TenantSettingsService;
use Illuminate\Support\Facades\Storage;
```

- [ ] **Step 6: Run affected tests**

```bash
php artisan test --compact --filter=DocumentServiceTest
php artisan test --compact --filter=DocumentWebTest
php artisan test --compact --filter=DocumentApiTest
```

Expected: all pass.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Documents/Services/DocumentService.php
git commit -m "refactor: route DocumentService storage through StorageResolver"
```

---

## Task 6: Update ReportDeliveryService and GenerateReportJob

**Files:**
- Modify: `app/Reports/Services/ReportDeliveryService.php`
- Modify: `app/Reports/Jobs/GenerateReportJob.php`

**Interfaces:**
- Consumes: `StorageResolver::forTenant(int $tenantId): Filesystem`, `StorageResolver::forSystem(): Filesystem` (Task 1)

- [ ] **Step 1: Refactor ReportDeliveryService**

In `app/Reports/Services/ReportDeliveryService.php`:

1. Add imports:
```php
use App\Storage\StorageResolver;
```

2. Add constructor:
```php
public function __construct(
    private readonly StorageResolver $resolver,
) {}
```

3. Rename `uploadToS3()` → `uploadToStorage()` and replace the body:
```php
public function uploadToStorage(Report $report, ?string $basePath): void
{
    if ($report->file_path === null) {
        return;
    }

    $disk = $report->tenant_id !== null
        ? $this->resolver->forTenant($report->tenant_id)
        : $this->resolver->forSystem();

    $filename = basename($report->file_path);
    $destination = rtrim($basePath ?? 'reports', '/').'/'.now()->format('Y/m/d').'/'.$filename;

    $contents = $disk->get($report->file_path);

    if ($contents === null) {
        throw new RuntimeException("Report file not found at {$report->file_path}.");
    }

    if ($disk->put($destination, $contents) === false) {
        throw new RuntimeException("Failed to upload report to storage at {$destination}.");
    }
}
```

4. Remove `use Illuminate\Support\Facades\Storage;` if no other Storage references remain.

- [ ] **Step 2: Update GenerateReportJob call sites**

In `app/Reports/Jobs/GenerateReportJob.php`, replace both `uploadToS3` calls with `uploadToStorage`:

```php
// Line ~99:
ReportDelivery::S3 => $deliveryService->uploadToStorage($report, $parameters['s3_path'] ?? null),

// Line ~117:
$deliveryService->uploadToStorage($report, $parameters['s3_path'] ?? null);
```

- [ ] **Step 3: Run affected tests**

```bash
php artisan test --compact --filter=GenerateReportJob
php artisan test --compact --filter=ReportDeliveryService
```

Expected: all pass.

- [ ] **Step 4: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Reports/Services/ReportDeliveryService.php app/Reports/Jobs/GenerateReportJob.php
git commit -m "refactor: rename uploadToS3 to uploadToStorage, route through StorageResolver"
```

---

## Task 7: Update Profile Picture Storage

**Files:**
- Modify: `app/Profile/Http/Controllers/ProfileController.php`
- Modify: `app/Profile/Http/Controllers/ProfileApiController.php`
- Modify: `app/Models/Central/User.php`
- Modify: `app/Http/Resources/UserResource.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`

**Interfaces:**
- Consumes: `StorageResolver::forSystem(): Filesystem` (Task 1)

- [ ] **Step 1: Update ProfileController**

In `app/Profile/Http/Controllers/ProfileController.php`:

1. Add import:
```php
use App\Storage\StorageResolver;
```

2. Add constructor:
```php
public function __construct(
    private readonly StorageResolver $resolver,
) {}
```

3. In `updatePicture()` (or equivalent method), replace:
```php
if ($user->profile_picture) {
    Storage::disk('public')->delete($user->profile_picture);
}
$path = $request->file('profile_picture')->storePublicly('profile-pictures', 'public');
```

With:
```php
$disk = $this->resolver->forSystem();

if ($user->profile_picture) {
    $disk->delete($user->profile_picture);
}

$file = $request->file('profile_picture');
$path = 'profile-pictures/'.$file->hashName();
$disk->put($path, $file->get(), 'public');
```

4. Remove `use Illuminate\Support\Facades\Storage;` if no other references remain.

- [ ] **Step 2: Update ProfileApiController**

Same changes as Step 1 — in `app/Profile/Http/Controllers/ProfileApiController.php`:

1. Add import:
```php
use App\Storage\StorageResolver;
```

2. Add constructor:
```php
public function __construct(
    private readonly StorageResolver $resolver,
) {}
```

3. Replace the upload block:
```php
$disk = $this->resolver->forSystem();

if ($user->profile_picture) {
    $disk->delete($user->profile_picture);
}

$file = $request->file('profile_picture');
$path = 'profile-pictures/'.$file->hashName();
$disk->put($path, $file->get(), 'public');
```

4. Remove `use Illuminate\Support\Facades\Storage;` if no other references remain.

- [ ] **Step 3: Update User model accessor**

In `app/Models/Central/User.php`, replace:
```php
? Storage::disk('public')->url($this->profile_picture)
```

With:
```php
? app(\App\Storage\StorageResolver::class)->forSystem()->url($this->profile_picture)
```

Remove `use Illuminate\Support\Facades\Storage;` from the model if no other `Storage::` references remain.

- [ ] **Step 4: Update UserResource**

In `app/Http/Resources/UserResource.php`, replace:
```php
? Storage::disk('public')->url($this->profile_picture)
```

With:
```php
? app(\App\Storage\StorageResolver::class)->forSystem()->url($this->profile_picture)
```

Remove the `Storage` import if no longer used.

- [ ] **Step 5: Update HandleInertiaRequests middleware**

In `app/Http/Middleware/HandleInertiaRequests.php`:

1. Add import:
```php
use App\Storage\StorageResolver;
```

2. Add constructor injection:
```php
public function __construct(
    private readonly StorageResolver $resolver,
) {}
```

3. Replace:
```php
? Storage::disk('public')->url($user->profile_picture)
```

With:
```php
? $this->resolver->forSystem()->url($user->profile_picture)
```

4. Remove `use Illuminate\Support\Facades\Storage;` if no other references remain.

- [ ] **Step 6: Run affected tests**

```bash
php artisan test --compact --filter=ProfileWebTest
php artisan test --compact --filter=ProfileApiTest
php artisan test --compact --filter=ProfileServiceTest
```

Expected: all pass.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add \
  app/Profile/Http/Controllers/ProfileController.php \
  app/Profile/Http/Controllers/ProfileApiController.php \
  app/Models/Central/User.php \
  app/Http/Resources/UserResource.php \
  app/Http/Middleware/HandleInertiaRequests.php
git commit -m "refactor: route profile picture storage through StorageResolver::forSystem()"
```

---

## Final Verification

- [ ] **Run the full test suite**

```bash
php artisan test --compact
```

Expected: all tests pass, no regressions.

- [ ] **Verify no hardcoded disk references remain**

```bash
grep -rn "Storage::disk('reports')\|Storage::disk('s3')\|Storage::disk('public')" app/ --include="*.php"
```

Expected: no results (except possibly in test files that explicitly test a specific disk).
