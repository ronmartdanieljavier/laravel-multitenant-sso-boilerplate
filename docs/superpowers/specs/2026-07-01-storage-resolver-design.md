# Storage Resolver Design

**Date:** 2026-07-01  
**Status:** Approved

## Overview

Introduce a `StorageResolver` service that resolves the correct Laravel `Filesystem` disk at runtime based on a priority chain. Tenant file operations use **Tenant → System → ENV**. Admin/system file operations use **System → ENV**.

This replaces all hardcoded `Storage::disk('reports')`, `Storage::disk('s3')`, and the partially-correct `resolveDisk()` method in `TenantSettingsService`.

---

## Priority Chains

| Context | Chain |
|---|---|
| Tenant files (reports, documents, uploads) | Tenant settings → System settings → ENV |
| Admin/system files (system-level branding) | System settings → ENV |

**ENV fallback** resolves to `Storage::disk(config('filesystems.default'))`, which reads the `FILESYSTEM_DISK` environment variable (defaults to `local`).

---

## Supported Drivers

| Driver | Tenant settings | System settings | ENV fallback |
|---|---|---|---|
| `s3` | `s3_key`, `s3_secret`, `s3_region`, `s3_bucket`, `s3_url` | same keys | `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_URL` |
| `r2` | `r2_access_key`, `r2_secret`, `r2_account_id`, `r2_bucket`, `r2_url` | same keys | env equivalents |
| `gcs` | — (not in tenant settings) | `gcs_project_id`, `gcs_key_json`, `gcs_bucket`, `gcs_url` | env equivalents |
| `ftp` | — | `ftp_host`, `ftp_port`, `ftp_username`, `ftp_password`, `ftp_root`, `ftp_passive` | env equivalents |
| `sftp` | — | `sftp_host`, `sftp_port`, `sftp_username`, `sftp_password`, `sftp_private_key`, `sftp_root` | env equivalents |
| `local` | — | — | `storage_path('app')` |

---

## New Component

### `app/Storage/StorageResolver.php`

```
StorageResolver
  - __construct(TenantSettingRepository $repository)
  + forTenant(int $tenantId): Filesystem
  + forSystem(): Filesystem
  - buildDisk(string $driver, ?callable $tenantGet): Filesystem
  - envFallback(): Filesystem
```

- `forTenant(int $tenantId)` — reads the tenant's `storage_driver`, then calls `buildDisk()` with a tenant getter closure and `SystemSetting::get()` as per-credential fallback.
- `forSystem()` — calls `buildDisk()` with no tenant getter; system values only, then ENV.
- `buildDisk(string $driver, ?callable $tenantGet)` — constructs the config array for the given driver using `$tenantGet($key) ?? SystemSetting::get($key) ?? env($envKey)` per credential. Returns `Storage::build($config)`.
- `envFallback()` — returns `Storage::disk(config('filesystems.default'))`.

**Error handling:** If a required credential is missing after the full resolution chain, `buildDisk()` throws `\InvalidArgumentException` with a descriptive message. No silent fallback to local when a cloud driver is configured — fail loud.

---

## Call Site Changes

| File | Current | Change |
|---|---|---|
| `TenantSettingsService::resolveDisk()` | builds disk inline | delegates to `StorageResolver::forTenant()` |
| `TenantSettingsService::uploadLogo()` | hardcodes `Storage::disk('public')` | uses `StorageResolver::forTenant()` |
| `ReportFileService` | hardcodes `Storage::disk('reports')` | injects `StorageResolver`, calls `forTenant($report->tenant_id)` |
| `ReportController::download()` | hardcodes `Storage::disk('reports')` | `StorageResolver::forTenant($report->tenant_id)` |
| `ReportController::destroy()` | hardcodes `Storage::disk('reports')` | `StorageResolver::forTenant($report->tenant_id)` |
| `DocumentService::createFromReport()` | hardcodes `Storage::disk('reports')` | `StorageResolver::forTenant($report->tenant_id)` |
| `DocumentService::downloadZipResponse()` | mixes `reports` disk and `resolveDisk()` | unified via `StorageResolver::forTenant()` |
| `ReportDeliveryService::uploadToS3()` | hardcodes `Storage::disk('s3')` | renamed `uploadToStorage()`, uses `StorageResolver::forTenant($report->tenant_id)` |
| `ProfileController::updatePicture()` | hardcodes `Storage::disk('public')` | uses `StorageResolver::forSystem()` |
| `ProfileApiController::updatePicture()` | hardcodes `Storage::disk('public')` | uses `StorageResolver::forSystem()` |
| `User::profilePictureUrl` accessor | hardcodes `Storage::disk('public')->url()` | uses `StorageResolver::forSystem()->url()` |
| `UserResource::profilePictureUrl` | hardcodes `Storage::disk('public')->url()` | uses `StorageResolver::forSystem()->url()` |
| `HandleInertiaRequests` profile picture URL | hardcodes `Storage::disk('public')->url()` | uses `StorageResolver::forSystem()->url()` |

**Out of scope:** System branding `logo_url` and `favicon_url` in `SystemSettingsData` — these are plain URL string fields (admins paste a URL, no file upload involved), so no `Storage` call exists to route through the resolver.

---

## Testing

**`app/Storage/Tests/StorageResolverTest.php`** (PHPUnit feature test)

Scenarios for `forTenant()`:
- Tenant `storage_driver` set → uses tenant credentials
- Tenant `storage_driver` empty, system `storage_driver` set → uses system credentials
- Both empty → ENV fallback disk returned
- Missing required credential after full chain → throws `InvalidArgumentException`

Scenarios for `forSystem()`:
- System `storage_driver` set → uses system credentials
- System `storage_driver` empty → ENV fallback disk returned

Existing tests for `DocumentService`, `ReportFileService`, `TenantSettingsService`, `ProfileController`, and `ProfileApiController` must be updated to mock/stub `StorageResolver` where appropriate.

---

## Out of Scope

- System branding logo/favicon URLs stored in `SystemSettingsData` — these are plain URL string fields, no file upload or `Storage` call involved.
- The `config/filesystems.php` `reports` disk definition — it remains as a local default but is no longer the primary code path.
