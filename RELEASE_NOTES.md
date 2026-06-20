# Release Notes

---

## [Unreleased] — In Progress

### Planned
- Tenant health dashboard in admin
- PHPStan level raised beyond 5

---

## [1.8.0] — 2026-06-20

### Added
- **Default apps and admin account seeded via migration** — a fresh `php artisan migrate` now produces a fully working install with no manual setup:
  - Migration `2026_06_20_050340_seed_default_admin_user` seeds two apps and one superuser after the schema is in place; all inserts use `insertOrIgnore` so the migration is safe to re-run
  - **Two default apps** created when `apps` table is empty:
    - `admin` (`APP_URL/admin`) — central administration; manages users, apps, tenants, and system settings via the central DB
    - `tenant` (`APP_URL/tenant`) — tenant portal; connects to a tenant database per request
  - **Default admin user** — credentials from `.env` (`ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`); falls back to `Admin` / `admin@example.com` / `password`
  - Admin user is assigned `role = admin` on both the `admin` and `tenant` apps via `user_apps`
  - Admin user is linked to the Demo Tenant (`slug = demo`) under the `tenant` app as the default tenant with `role = admin` via `user_app_tenants`; the `admin` app intentionally has no tenant DB rows
  - `down()` removes the user, all `user_apps`/`user_app_tenants` records, and the two seeded apps on rollback
- **Two-dimensional permission model clarified** — users can hold access to apps and tenant databases independently:
  - **Admin only** — `user_apps` row for `admin`; manages tenants through the central DB with no tenant DB connection
  - **Tenant only** — `user_apps` row for `tenant` + one `user_app_tenants` row per accessible tenant database
  - **Both** — rows in both apps; full access to the admin panel and one or more tenant databases
- **Postman collection updated** — `admin_email` and `admin_password` collection variables added; Login request body uses `{{admin_email}}` / `{{admin_password}}`

---

## [1.7.0] — 2026-06-20

### Added
- **Per-tenant scheduled report subscriptions (email/S3 delivery)** — tenants can subscribe to recurring reports that are generated and delivered automatically on a configurable schedule:
  - `report_subscriptions` tenant table — columns: `type`, `format`, `frequency` (`daily` / `weekly` / `monthly`), `delivery` (`email` / `s3` / `email_and_s3`), `recipients` (JSON array of email addresses), `s3_path` (nullable S3 key prefix), `is_active`, `last_dispatched_at`; composite index on `(is_active, frequency, last_dispatched_at)` for efficient due-subscription queries
  - `ReportSubscription` model (`app/Models/Tenant/ReportSubscription.php`) — `$connection = 'tenant'`, enum casts for `ReportFormat`, `ReportFrequency`, `ReportDelivery`, `isDue()` helper compares `last_dispatched_at` against the start of the current day / week / month
  - `ReportFrequency` enum (`app/Reports/Enums/ReportFrequency.php`) — `Daily`, `Weekly`, `Monthly`; each case carries its own `isDue(?Carbon)` logic
  - `ReportDelivery` enum extended — new `S3` and `EmailAndS3` cases added alongside the existing `Download`, `Email`, and `None`
  - `DispatchScheduledReportsCommand` (`php artisan reports:dispatch-subscriptions`) — iterates all active tenants, connects to each tenant DB, loads due subscriptions, creates a central `Report` record per subscription (with `subscription_id`, `recipients`, and `s3_path` stored in `parameters`), dispatches `GenerateReportJob`, and stamps `last_dispatched_at`; supports `--tenant=slug` to scope to a single tenant
  - Scheduled at `everyMinute()->withoutOverlapping()->onOneServer()->runInBackground()` in `bootstrap/app.php` via `withSchedule()`
  - `GenerateReportJob` extended — post-generation delivery now routes on `ReportDelivery`: subscription email reports go to `parameters.recipients` via `ReportDeliveryService::sendToRecipients()`; S3 reports upload via `ReportDeliveryService::uploadToS3()`; `email_and_s3` does both; on-demand user reports (`Download` / `Email` with no subscription context) behave as before
  - `ReportDeliveryService` (`app/Reports/Services/ReportDeliveryService.php`) — `sendToRecipients(Report, string[])` queues `ScheduledReportMail`; `uploadToS3(Report, ?string)` streams the generated file to `s3://{s3_path}/{Y/m/d}/{filename}`
  - `ScheduledReportMail` (`app/Reports/Mail/ScheduledReportMail.php`) — implements `ShouldQueue`; attaches the generated file via `Attachment::fromStorage()`; subject includes the report type
  - `ReportSubscriptionController` — CRUD: `index` (paginated), `store`, `show`, `update` (partial — all fields optional, use `is_active: false` to pause), `destroy`
  - `StoreReportSubscriptionRequest` / `UpdateReportSubscriptionRequest` — validate `type`, `format`, `frequency`, `delivery`, `recipients` (array of valid email addresses), `s3_path`, `is_active`
  - `ReportSubscriptionPolicy` registered in `AppServiceProvider`
  - Routes registered under `/api/reports/subscriptions` (auth:sanctum); placed before `/{report}` in the route group to prevent the wildcard binding from intercepting subscription paths
  - `config/database.php` — static `tenant` connection entry driven by `DB_TENANT_DRIVER` / `DB_TENANT_DATABASE` env vars; `phpunit.xml` sets these to `sqlite` / `:memory:` for isolated tenant DB testing
  - `ReportSubscriptionFactory` with `inactive()`, `daily()`, `weekly()`, `monthly()`, `emailDelivery()`, `s3Delivery()` states
  - **14 PHPUnit feature tests** in `tests/Feature/Reports/ReportSubscriptionTest.php`:
    - `test_authenticated_user_can_list_subscriptions` — asserts 200 with paginated structure
    - `test_unauthenticated_user_cannot_list_subscriptions` — asserts 401
    - `test_authenticated_user_can_create_subscription` — asserts 201 with correct payload
    - `test_subscription_creation_validates_required_fields` — asserts 422 with field errors
    - `test_subscription_creation_validates_recipient_emails` — asserts 422 when `recipients.*` is not a valid email
    - `test_authenticated_user_can_update_subscription` — asserts `is_active` and `frequency` update correctly
    - `test_authenticated_user_can_delete_subscription` — asserts 204 and record removed from tenant DB
    - `test_daily_subscription_is_due_when_never_dispatched` — asserts `null` `last_dispatched_at` means due
    - `test_daily_subscription_is_due_after_yesterday` — asserts yesterday's dispatch is stale
    - `test_daily_subscription_is_not_due_when_dispatched_today` — asserts same-day dispatch blocks re-dispatch
    - `test_weekly_subscription_is_due_after_last_week` — asserts previous week triggers dispatch
    - `test_monthly_subscription_is_due_after_last_month` — asserts previous month triggers dispatch
    - `test_dispatch_command_skips_inactive_tenants` — asserts no jobs pushed when all tenants inactive
    - `test_dispatch_command_reports_no_active_tenants` — asserts command exits successfully with no tenants

### Updated
- **Postman collection** — new "Report Subscriptions" folder with 5 requests (List, Create, Get, Update, Delete); `subscription_id` collection variable auto-saved by the Create request's test script; delivery field descriptions updated to include `s3` and `email_and_s3`

---

## [1.6.0] — 2026-06-20

### Added
- **Tenant migration version tracking** — after every `tenant:migrate` run, applied migrations are synced from each tenant's `migrations` table into a new central `tenant_migration_versions` table, giving a single place to query the migration state of every tenant database:
  - `tenant_migration_versions` central table — columns: `tenant_id` (FK, cascades on delete), `migration`, `batch`, `migrated_at`; unique constraint on `(tenant_id, migration)` prevents duplicates
  - `TenantMigrationVersion` model (`app/Models/Central/TenantMigrationVersion.php`) — `belongsTo(Tenant)`, fillable, `migrated_at` cast to datetime, no timestamps
  - `Tenant::migrationVersions()` — new `HasMany` relationship for querying a tenant's migration history
  - `TenantMigrateCommand` — after each successful migration run, `syncMigrationVersions()` reads the tenant's `migrations` table and upserts every row into `tenant_migration_versions`; skipped gracefully when the tenant's `migrations` table does not exist yet
  - `php artisan tenant:migrate:status` — new command (`TenantMigrateStatusCommand`) that renders a console table showing, for each active tenant: name, slug, applied/total migration count, whether fully up to date, and the latest applied migration; supports `--tenant=slug` to inspect a single tenant
  - **8 PHPUnit feature tests** in `tests/Feature/TenantMigrationVersionTest.php`:
    - `test_tenant_migration_version_can_be_created` — asserts DB record created and `tenant` relationship resolves
    - `test_tenant_migration_versions_deleted_when_tenant_deleted` — asserts cascade delete works
    - `test_tenant_has_migration_versions_relationship` — asserts `HasMany` returns all versions
    - `test_migration_version_enforces_unique_tenant_migration_pair` — asserts duplicate upsert throws `UniqueConstraintViolationException`
    - `test_migrate_status_command_shows_tenant_table` — asserts tenant name appears in command output
    - `test_migrate_status_command_filters_by_tenant_slug` — asserts `--tenant` flag scopes output correctly
    - `test_migrate_status_command_warns_when_no_tenants_found` — asserts warning when slug matches nothing
    - `test_migrate_status_command_shows_up_to_date_for_fully_migrated_tenant` — asserts `N/N` count shown when all migrations applied

---

## [1.5.0] — 2026-06-20

### Added
- **Per-tenant read replica support** — each tenant can optionally be configured with a dedicated read replica database; SELECT queries are automatically routed to the replica while all writes continue to hit the primary:
  - Four new nullable columns on the `tenants` table: `read_replica_host`, `read_replica_port`, `read_replica_username`, `read_replica_password` (password stored encrypted)
  - `Tenant::hasReadReplica()` helper returns `true` when `read_replica_host` is set
  - `ResolveTenantDatabase` middleware now builds the `tenant` connection config dynamically — if a replica is configured, the connection uses Laravel's `read` / `write` array split with `sticky: true`; tenants without a replica use the existing flat config and are unaffected
  - `sticky: true` ensures that writes performed during a request are immediately readable without hitting the replica, avoiding replication-lag reads within the same request cycle
  - Replica port defaults to the primary `db_port` when `read_replica_port` is `null`
  - Replica credentials (`read_replica_username` / `read_replica_password`) are optional — when absent, the replica inherits the primary connection's credentials; when present, they are applied exclusively to the `read` connection
  - `TenantFactory` updated — all four replica fields default to `null` so existing factory usage is unchanged
  - **4 new PHPUnit feature tests** in `ResolveTenantDatabaseMiddlewareTest`:
    - `test_configures_read_write_split_when_read_replica_present` — asserts `read.host`, `read.port`, `write.host`, `write.port`, and `sticky` are set correctly
    - `test_read_replica_inherits_primary_port_when_replica_port_not_set` — asserts replica falls back to primary port
    - `test_read_replica_supports_separate_credentials` — asserts `read.username` and `read.password` are applied when set
    - `test_no_read_write_split_when_no_replica` — asserts the flat config is used and `read` / `write` / `sticky` keys are absent when no replica is configured

---

## [1.4.1] — 2026-06-20

### Fixed
- **`batch_id` spoofing removed** — `StoreReportRequest` no longer accepts `batch_id` from user input; standalone reports always have `batch_id = null`. Batch IDs are server-generated exclusively in `ReportController::batch()` via `Str::uuid()`.
- **Batch format/delivery homogeneity enforced** — `StoreBatchReportRequest` now validates (via `after()`) that all items in a batch share the same `format` and `delivery`; mixed batches return `422`. This makes the post-completion ZIP and PDF-merge logic safe to apply uniformly.
- **ZIP path persisted after batch completion** — `GenerateReportBatchJob::handleBatchCompletion()` now captures the return value of `ReportFileService::zipFiles()` and writes the ZIP's storage path to the first report's `file_path`, making the batch archive retrievable via `GET /api/reports/{id}/download`.
- **Cancelled batch reports marked Failed** — when a Laravel Bus batch is cancelled, each job's report is now updated to `status = failed`, `error_message = 'Batch was cancelled.'`, and `completed_at` stamped, rather than remaining stuck in `Pending` or `Processing`.
- **`Storage::put` failure throws** — `ReportFileService::storeFile()` now throws a `RuntimeException` when `Storage::put()` returns `false`, propagating the error into `GenerateReportJob::failed()` so the report is marked `Failed` instead of recording a path to a file that was never written.
- **Duplicate `viewHorizon` gate resolved** — `AppServiceProvider` was defining `viewHorizon` first, then `HorizonServiceProvider::gate()` overwrote it with an empty allowlist, blocking access everywhere. The gate is now defined only in `HorizonServiceProvider::gate()` (its intended home); `AppServiceProvider` retains only the `Horizon::auth()` callback that delegates to it.

### Updated
- **Postman collection** — added a full `Reports` folder covering all 6 endpoints (`List`, `Dispatch Single`, `Dispatch Batch`, `Get Status`, `Download`, `Delete`) with example request bodies, example responses (including 422 error cases), and a `report_id` collection variable auto-saved by the dispatch request's test script.

---

## [1.4.0] — 2026-06-20

### Added
- **Laravel Horizon + Redis async report queue** — full async reporting engine under `App\Reports\`:
  - `laravel/horizon` installed; `config/horizon.php` configured with a dedicated `report-worker` supervisor on the `reports` queue (5 max processes, 300 s timeout, 3 retries)
  - `reports` central DB table — UUID primary key, tracks `type`, `format`, `delivery`, `status`, `parameters` (JSON), `file_path`, `error_message`, `batch_id`, `started_at`, `completed_at`
  - `Report` model (`app/Models/Central/Report.php`) with `HasUuids`, enum casts for `ReportStatus`, `ReportFormat`, and `ReportDelivery`, and `belongsTo` relationships to `User` and `Tenant`
  - `ReportStatus` enum — `Pending`, `Processing`, `Success`, `Failed`
  - `ReportFormat` enum — `Screen`, `Pdf`, `Excel`
  - `ReportDelivery` enum — `Download`, `Email`, `None`
  - `GenerateReportJob` (`ShouldQueue`, `Batchable`) — queued on `reports`, sets status through `Pending → Processing → Success`; `failed()` handler records `error_message` and sets `Failed`
  - `GenerateReportBatchJob` — dispatches multiple `GenerateReportJob` instances via `Bus::batch()`; post-completion callback handles ZIP packaging of batch output
  - `ReportGeneratorFactory` — resolves the correct generator by `ReportFormat`
  - `ScreenReportGenerator` — fully working; returns JSON data payload
  - `PdfReportGenerator` — stub (requires `barryvdh/laravel-dompdf` or `spatie/laravel-pdf`)
  - `ExcelReportGenerator` — stub (requires `maatwebsite/excel`)
  - `ReportFileService` — `storeFile()`, `zipFiles()` (ZipArchive, working), `mergePdfs()` (stub, requires `setasign/fpdi`)
  - `ReportReadyMail` mailable + `resources/views/emails/reports/ready.blade.php` for email delivery
  - `ReportPolicy` — authorizes `view`, `download`, `delete` to the report's owner only
  - `ReportController` with six endpoints: `index`, `store`, `batch`, `show`, `download`, `destroy`
  - `StoreReportRequest` / `StoreBatchReportRequest` form request validation (max 50 reports per batch)
  - `ReportFactory` with states: `pending`, `processing`, `success`, `failed`, `screen`, `pdf`, `excel`
  - **17 PHPUnit feature tests** across `ReportDispatchTest`, `GenerateReportJobTest`, `ReportBatchTest`, `ReportDownloadTest`
- **Horizon Docker service** — `docker-compose.yml` gains a dedicated `horizon` container running `php artisan horizon` with a health check; restarts automatically
- **`viewHorizon` gate** — Horizon dashboard at `/horizon` gated to `local` environment by default; update the gate in `AppServiceProvider` to restrict to admin users once `is_admin` is added to the `users` table

### API routes added

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/reports` | List the authenticated user's reports (paginated) |
| `POST` | `/api/reports` | Dispatch a single report job |
| `POST` | `/api/reports/batch` | Dispatch up to 50 report jobs as a batch |
| `GET` | `/api/reports/{id}` | Get status and result data for a report |
| `GET` | `/api/reports/{id}/download` | Stream the generated file (success only) |
| `DELETE` | `/api/reports/{id}` | Delete a report and its stored file |

### Pending (Phase 4 — requires additional packages)

| Feature | Package |
|---|---|
| PDF generation | `barryvdh/laravel-dompdf` or `spatie/laravel-pdf` |
| Excel generation | `maatwebsite/excel` |
| PDF merging | `setasign/fpdi` |
| Horizon restricted to admins | `is_admin` column on `users` table |

---

## [1.3.0] — 2026-06-20

### Added
- **Inertia shared props — active tenant + permissions on every page** — `HandleInertiaRequests::share()` now exposes four props automatically on every Inertia response:
  - `auth.user` — the authenticated `User` model
  - `tenant` — the resolved `Tenant` model (lazy closure; `null` on non-tenant routes)
  - `app` — the resolved `App` model (lazy closure; `null` on non-tenant routes)
  - `role` — the user's `Role` enum value for the current app + tenant context (lazy closure; `null` on non-tenant routes)
  - Props are populated from `$request->attributes` set by `ResolveTenantDatabase` middleware, so no extra DB queries are issued
  - Lazy closures ensure the props evaluate to `null` on unauthenticated or non-tenant pages without errors

---

## [1.2.0] — 2026-06-20

### Added
- **Two-dimensional permission enforcement** — `ResolveTenantDatabase` middleware now enforces both permission dimensions on every tenant request:
  - **Dimension 1 (App)** — reads `X-App` header, verifies the app is active, checks the Sanctum token carries the `app:{slug}` ability, and confirms the user has a `user_apps` record for that app; returns `400` if the header is missing, `403` on any access failure
  - **Dimension 2 (Tenant)** — tenant lookup is now scoped to the resolved app via `user_app_tenants.app_id`, preventing cross-app tenant access; returns `403` if the tenant is inactive, not found, or the user's access is for a different app
  - Stores `current_app` (App model), `current_tenant` (Tenant model), and `current_role` (Role enum) on `$request->attributes` for use by downstream controllers and middleware
- **`RequireRole` middleware** — `app/Http/Middleware/RequireRole.php` enforces role-level access on individual routes:
  - Accepts one or more role parameters (e.g. `RequireRole::class.':admin'` or `RequireRole::class.':admin,user'`)
  - Applies role hierarchy: `Admin > User > Readonly` — a user passes if their tenant-level role meets or exceeds any of the specified roles
  - Returns `403` if no role is set on the request (middleware run out of order) or if the user's role is insufficient
  - Usage: `Route::middleware(['auth:sanctum', ResolveTenantDatabase::class, RequireRole::class.':admin'])->group(...)`
- **`ResolveTenantDatabase` middleware tests** — updated and extended `tests/Feature/Tenant/ResolveTenantDatabaseMiddlewareTest.php`:
  - Updated existing tests to supply `X-App` header and Sanctum token abilities
  - Added `test_returns_bad_request_when_no_app_header` — 400 when `X-App` is absent
  - Added `test_returns_forbidden_when_app_not_found` — 403 when slug does not match any active app
  - Added `test_returns_forbidden_when_token_lacks_app_ability` — 403 when token was not issued with the `app:{slug}` ability
  - Added `test_returns_forbidden_when_user_has_no_user_app_record` — 403 when `user_apps` record is missing despite having a `user_app_tenants` entry
  - Added `test_returns_forbidden_when_tenant_belongs_to_different_app` — 403 when user has tenant access via app A but the request is for app B
  - Added `test_stores_current_app_and_role_on_request` — asserts `current_app`, `current_tenant`, and `current_role` are all set correctly on success
- **`RequireRole` middleware tests** — `tests/Feature/Tenant/RequireRoleMiddlewareTest.php` with 11 PHPUnit tests:
  - Admin passes admin, user, and readonly checks
  - User passes user and readonly checks; fails admin check
  - Readonly passes readonly check; fails user and admin checks
  - Returns 403 when no `current_role` is set on the request
  - Passes when the user's role matches any of multiple accepted roles

---

## [1.1.0] — 2026-06-20

### Added
- **`ResolveTenantDatabase` middleware** — `app/Http/Middleware/ResolveTenantDatabase.php` resolves the active tenant database connection per request:
  - Reads the `X-Tenant` request header for the tenant slug
  - Returns `400 Bad Request` if the header is absent
  - Returns `403 Forbidden` if the tenant is inactive, not found, or the authenticated user has no access to it
  - Dynamically registers a `tenant` MySQL connection in `database.connections.tenant` using the tenant's stored `db_host`, `db_port`, `db_name`, `db_username`, and `db_password`
  - Calls `DB::purge('tenant')` to flush any stale connection so the new config takes effect immediately
  - Merges `current_tenant` onto the request for use by downstream controllers
- **Middleware applied to tenant API routes** — `app/Tenant/Routes/api_tenant.php` now applies `['auth:sanctum', ResolveTenantDatabase::class]` so all tenant endpoints require both authentication and a valid `X-Tenant` header
- **PHPStan config** — added `phpVersion: 80500` to `phpstan.neon` so static analysis targets PHP 8.5; PHPStan passes at level 5 with zero errors (`--memory-limit=512M` required due to default 128M PHP CLI limit)
- **Middleware tests** — `tests/Feature/Tenant/ResolveTenantDatabaseMiddlewareTest.php` with 5 PHPUnit feature tests covering: missing header (400), tenant not found (403), user has no access (403), tenant inactive (403), and successful connection configuration (200 + correct `database.connections.tenant` values set)

---

## [1.0.0] — 2026-06-19

### Added
- **Docker Compose fixes** — resolved duplicate `depends_on` key in `docker-compose.yml` that prevented the stack from parsing
- **Dockerfile fixes for PHP 8.5** — removed `pdo` (bundled in PHP 8.4+) and `opcache` (already compiled in) from `docker-php-ext-install`; added `zlib-dev` and `icu-libs` system dependencies required to compile `zip` and `intl` extensions
- **`TenantSeeder`** — `database/seeders/TenantSeeder.php` creates a Demo Tenant using `Tenant::factory()` so `db_password` is stored encrypted via the model's `encrypted` cast; called from `DatabaseSeeder`
- **Demo Tenant seeded in migration** — `create_tenants_table` migration now inserts the Demo Tenant row via `Tenant::create()` immediately after the table is created, ensuring a tenant exists before `tenant:migrate` is run
- **Tenant gets its own database** — Demo Tenant seeded with `db_name = tenant_demo` instead of the central database name
- **Auto-create tenant database** — `TenantMigrateCommand` now creates the tenant database if it does not exist by connecting to the `postgres` maintenance database and issuing `CREATE DATABASE`

### Changed
- **`AppServiceProvider`** — removed `tenant` migration path from the default migrator so `php artisan migrate` only runs `database/migrations/central/`; tenant migrations must be run via `php artisan tenant:migrate`
- **`TenantMigrateCommand`** — switched driver from `mysql` to `pgsql` with correct PostgreSQL connection options (`charset utf8`, `sslmode prefer`)
- **Tenant migration order** — renamed migration files in `database/migrations/tenant/` to enforce correct dependency order: `companies` → `properties` → `floors` → `units` → `leases` → `lease_documents` → `lease_renewals`
- **`lease_documents` migration** — replaced `foreignId('uploaded_by')->constrained('users')` with `unsignedBigInteger('uploaded_by')->nullable()` since `users` lives in the central database and cannot be referenced by a foreign key from a separate tenant database

---

## [0.9.0] — 2026-06-19

### Changed
- **Central models moved to `app/Models/Central/`** — `App`, `Tenant`, `User`, `UserApp`, `UserAppTenant`, and `SystemSetting` relocated from `app/Auth/Models/` to `app/Models/Central/`; namespace updated from `App\Auth\Models` to `App\Models\Central` across all files that reference these models

---

## [0.8.0] — 2026-06-19

### Added
- **Separated migration directories** — migrations split into two dedicated paths:
  - `database/migrations/central/` — all central DB tables (users, tenants, apps, user_apps, user_app_tenants, system_settings, cache, jobs, personal_access_tokens)
  - `database/migrations/tenant/` — tenant-specific tables run per-tenant database
- **`php artisan central:migrate`** — runs migrations only against the central database (`database/migrations/central/`); supports `--fresh`, `--seed`, `--rollback`, `--step`, `--force`
- **`php artisan tenant:migrate`** — reads all active tenant rows from the central DB, dynamically configures a per-tenant database connection using each tenant's stored credentials (`db_host`, `db_port`, `db_name`, `db_username`, `db_password`), and runs `database/migrations/tenant/` against every tenant; supports `--tenant=slug` to target a single tenant, plus `--fresh`, `--seed`, `--rollback`, `--step`, `--force`
- **`php artisan migrate`** — unchanged command now runs both `central/` and `tenant/` paths on the default connection via `AppServiceProvider`

### Changed
- **`AppServiceProvider`** — registers both migration paths (`central/` and `tenant/`) with the migrator so the default `migrate` command covers both directories
- **`app/Console/Commands/`** — new directory created for `CentralMigrateCommand` and `TenantMigrateCommand`

---

## [0.7.0] — 2026-06-19

### Added
- **Postman collection** — `postman/laravel-multitenant-sso.postman_collection.json` covering all three SSO endpoints
  - `POST /api/login` — with example request body (email + password) and example responses (200 with token payload, 401 invalid credentials)
  - `POST /api/logout` — requires `Authorization: Bearer {{token}}`
  - `GET /api/apps` — returns accessible apps and tenant clients for the authenticated user
  - `base_url` and `token` collection variables; Login request includes a test script that auto-saves the token to `{{token}}`

---

## [0.6.0] — 2026-06-19

### Added
- **Docker environment** — full local dev stack with Nginx, PHP-FPM 8.5, PostgreSQL 17, and Redis 7
  - `docker-compose.yml` — four services (`nginx`, `php`, `postgres`, `redis`) on a shared `app` bridge network
  - `docker/php/Dockerfile` — PHP 8.5-FPM Alpine image with `pdo_pgsql`, `pgsql`, `redis` (PECL), `mbstring`, `zip`, `bcmath`, `intl`, `opcache`; Composer 2 included
  - `docker/nginx/default.conf` — Nginx server block serving `public/`, proxying PHP to `php:9000` via FastCGI
  - `.env.docker` — pre-configured environment file (`DB_CONNECTION=pgsql`, `DB_HOST=postgres`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `REDIS_HOST=redis`)
  - `.dockerignore` — excludes `node_modules`, `vendor`, logs, cache, and test artifacts from the image build
  - PostgreSQL and Redis data persisted in named Docker volumes (`postgres_data`, `redis_data`)
  - Health checks on `postgres` and `redis` — `php` service waits for both before starting

### Changed
- **`.env`** — updated to match Docker configuration: `DB_CONNECTION=pgsql`, `DB_HOST=postgres`, `DB_PORT=5432`, `DB_DATABASE=laravel`, `DB_USERNAME=laravel`, `DB_PASSWORD=secret`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `REDIS_HOST=redis`

---

## [0.5.1] — 2026-06-19

### Fixed
- **Login form** — `form.post()` was pointing to `/api/login` (API middleware, no session/CSRF); corrected to `POST /login` on the web middleware stack
- **Protected routes** — `/admin`, `/client`, `/reports` were publicly accessible; wrapped in `auth` middleware so unauthenticated visitors are redirected to `/login`
- **POST /login handler** — added web route that authenticates via `Auth::attempt`, regenerates the session, and redirects to `/client`
- **E2E tests** — added shared `login()` helper (`resources/js/e2e/helpers/auth.js`) and wired it into `beforeEach` for all three protected-page specs; CI now runs `php artisan db:seed --force` so the test user exists
- **CI server readiness** — replaced `sleep 3` with `timeout 30 bash -c 'until curl -sf http://localhost:8000/up; do sleep 1; done'` to eliminate flaky E2E failures on loaded runners
- **`package.json`** — moved `vue` and `@inertiajs/vue3` from `devDependencies` to `dependencies` (runtime bundles required in production)
- **Dead import** — removed unused `beforeAll` from `Login/Index.test.js`
- **`.gitignore`** — added `test-results/` and `playwright-report/` to prevent Playwright artifacts from being committed

---

## [0.5.0] — 2026-06-19

### Added
- **Frontend testing** — Vitest 4 + Vue Test Utils 2 for component unit tests; Playwright 1.61 for E2E browser tests
  - 16 Vitest unit tests across all four page components (`Login`, `Admin`, `Client`, `Reports`) — render assertions, slot/prop checks, tab interaction
  - 4 Playwright E2E spec files (`login.spec.js`, `admin.spec.js`, `client.spec.js`, `reports.spec.js`) — full page visibility checks running against a live Laravel server
  - E2E specs live under `resources/js/e2e/`; unit tests co-located with each page as `*.test.js`
  - `playwright.config.js` at repo root — Chromium project, configurable `APP_URL`, CI retry support, Playwright report artifact upload on failure
- **npm test scripts** — `test:unit`, `test:unit:watch`, `test:unit:coverage`, `test:e2e`, `test:e2e:ui`, `test` (runs both)
- **CI jobs** — two new GitHub Actions jobs added to `ci.yml`:
  - `Frontend Unit Tests` — runs `npm run test:unit` via Vitest on every push/PR
  - `Frontend E2E Tests` — boots Laravel (`php artisan serve`), installs Playwright Chromium, runs `npm run test:e2e`; uploads `playwright-report/` artifact on failure

---

## [0.4.0] — 2026-06-19

### Added
- **Inertia.js + Vue 3 frontend** — server-side rendering bridge installed and configured across all four apps
  - `inertiajs/inertia-laravel` (composer) + `@inertiajs/vue3`, `vue 3`, `@vitejs/plugin-vue` (npm)
  - `HandleInertiaRequests` middleware registered in `bootstrap/app.php`
  - `resources/views/app.blade.php` root Inertia template
  - `resources/js/app.js` bootstraps Vue 3 + Inertia with dynamic page resolution via `import.meta.glob`
- **Landing pages** — dark-themed Vue 3 SFCs with Tailwind CSS for each app module:
  - `Login/Index.vue` — SSO login form with email/password fields, remember me, forgot password link
  - `Admin/Index.vue` — sidebar dashboard with stats cards and recent users table
  - `Client/Index.vue` — app-picker portal with four app cards and recent activity feed
  - `Reports/Index.vue` — reports suite with summary stats, tabbed interface, and downloadable report rows
- **Web routes** — `routes/web.php` updated with named Inertia routes for `/login`, `/admin`, `/client`, `/reports`
- **CI** — `Frontend Build` job added to `ci.yml` running `npm run build` on every push and PR
- **npm scripts** — `dev` and `build` scripts added to `package.json`; `laravel-vite-plugin` and `@tailwindcss/vite` installed

---

## [0.3.0] — 2026-06-19

### Added
- **SSO backend** — full authentication flow under `App\Login\`
  - `POST /api/login` — validates credentials, issues Sanctum token with per-app abilities, returns user + app/client access list as DTOs
  - `POST /api/logout` — revokes the current token
  - `GET /api/apps` — returns the authenticated user's accessible apps and tenant clients
- **Central DB migrations** — `apps`, `clients`, `user_apps`, `user_app_clients`, `system_settings` tables
- **Models** — `App`, `Client`, `UserApp`, `UserAppClient`, `SystemSetting` in `App\Login\Models\`; `User` updated with `HasApiTokens` and app/client relationships
- **`Role` enum** — `Admin`, `User`, `Readonly` backed by `App\Login\Enums\Role`
- **DTOs with spatie/laravel-data 4.23** — `LoginCredentialsCoreData`, `AuthTokenCoreData`, `UserCoreData`, `AppAccessCoreData`, `ClientAccessCoreData` under `App\Login\Data\Core\`; global snake_case input/output mapping configured via published `config/data.php`
- **Actions** — `LoginAction` (authenticate + token issuance), `LoadUserAppsAction` (eager-loads app + client access into DTOs)
- **Form Request** — `LoginRequest` with validation and `toData()` helper
- **Modular route loading** — `routes/api.php` glob-loads all `app/*/Routes/api_*.php` files automatically; each app owns its routes
  - `app/Login/Routes/api_login.php` — SSO endpoints
  - `app/Admin/Routes/api_admin.php` — admin endpoints (stub)
  - `app/Client/Routes/api_client.php` — client endpoints (stub)
  - `app/Reports/Routes/api_reports.php` — reports endpoints (stub)
- **Collocated tests** — PHPUnit feature tests live in `app/Login/Tests/` alongside the module; registered as the `Login` test suite in `phpunit.xml`
- **Factories** — `UserFactory` updated to `App\Login\Models\User`; `AppFactory` and `ClientFactory` added under `Database\Factories\Login\`

---

## [0.2.0] — 2026-06-19

### Added
- Laravel 13.16.1 installed at repo root (PHP 8.3+)
- Laravel Boost 2.4 for starter kit scaffolding
- Laravel Sanctum 4.0 for API token authentication
- Tailwind CSS via `@tailwindcss/vite` plugin
- Bunny Fonts (`Instrument Sans`) via `laravel-vite-plugin`
- Vite build pipeline with hot-reload and storage view exclusion
- commitlint 21 + Husky 9 enforcing conventional commits across the repo
- `CLAUDE.md` with Claude Code context for agentic development workflows

---

## [0.1.0] — 2026-06-19

### Added
- Initial repository setup
- Project README with full architecture vision (monorepo, SSO, multi-tenant)
- commitlint configuration (`commitlint.config.js`) with conventional commit rules
- Husky pre-commit and commit-msg hooks
- `.gitignore` for Laravel and Node.js artifacts
