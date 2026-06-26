# Release Notes

---

## [2.24.0] — 2026-06-26

### Added

- **Document upload constraints — Phase 6.3** — configurable allowed file types and per-type maximum file sizes for tenant document uploads, enforced at both the system level and per-tenant override level.

  **Backend**

  - `SystemSettingsData` — 7 new nullable props: `uploadAllowedTypes`, `uploadMaxSizePdf`, `uploadMaxSizeDoc`, `uploadMaxSizeText`, `uploadMaxSizeExcel`, `uploadMaxSizeImage`, `uploadMaxSizeCsv`
  - `SystemSettingsService::getSettings()` — reads all 7 new keys from `system_settings`
  - `UpdateSystemSettingsRequest` — 7 new nullable validation rules (`upload_allowed_types`: string max:255; per-type sizes: integer 1–100)
  - `TenantSettingsData` — 7 tenant override props + 7 effective value props (e.g. `effectiveUploadAllowedTypes`, `effectiveUploadMaxSizePdf`, …)
  - `TenantSettingsService::getSettings()` — populates new upload props and resolves effective values (tenant override → system setting → hardcoded default)
  - `TenantSettingsService::resolveUploadConstraints(int $tenantId): array` — new method returning `{ allowedTypes: string[], maxSizes: Record<string, int> }`; resolves tenant override → system setting → hardcoded default (`pdf,doc,text,excel,image,csv` @ 5 MB each)
  - `UpdateTenantSettingsRequest` — same 7 nullable validation rules as system settings
  - `StoreDocumentRequest` — dynamically builds validation rules at request time by calling `TenantSettingsService::resolveUploadConstraints()`; uses a `mimetypes:` rule for the resolved MIME groups and a Closure rule that detects the file's MIME group and enforces the per-type size limit (bytes = MB × 1024 × 1024)
  - `DocumentController::index()` — now injects `TenantSettingsService` and passes `uploadConstraints` as an Inertia prop

  **Frontend**

  - `resources/js/Pages/Admin/Settings/Index.vue` — new "Document Upload Settings" card in the Storage tab: checkbox group to toggle allowed file type groups (pdf, doc, text, excel, image, csv) and a number input per group for the max file size in MB
  - `resources/js/Pages/Admin/Tenants/Settings.vue` — new "Document Upload Settings" card in the Storage tab: a master override toggle, then per-type checkboxes and MB inputs that are visible only when the override is active; `null` fields revert to system defaults at runtime
  - `resources/js/Pages/Documents/Index.vue` — consumes `uploadConstraints` prop; the file `<input>` gets a dynamic `:accept` attribute (mapped from type groups to extensions); a hint line below the input lists allowed types and their size caps

  **Tests**

  - `DocumentWebTest` — 4 new tests: upload constraint prop present, disallowed MIME type rejected (422), file exceeding per-type size rejected (422), allowed type within limit accepted
  - `DocumentApiTest` — 3 new tests: disallowed MIME type rejected, oversized file rejected, allowed type within limit accepted
  - `SystemSettingsWebTest` — 4 new tests: upload fields present in settings payload, upload settings can be saved, max size > 100 rejected, upload settings update endpoint responds correctly
  - `TenantSettingsWebTest` — 4 new tests: upload fields present in tenant settings payload, tenant upload settings can be saved, max size > 100 rejected, clearing settings removes DB rows
  - `TenantSettingsServiceTest` — 6 new tests: hardcoded defaults returned when nothing set, system settings override hardcoded defaults, tenant override wins over system setting, only allowed-type sizes included in result, effective allowed types falls back to system setting, effective max size uses tenant override

---

## [2.23.0] — 2026-06-26

### Added

- **Documents module — Phase 6.2** — new `app/Documents/` module following the standard layered architecture (`Controller → Service → Repository → Model`).

  **Backend**

  - `Document` model (`App\Models\Tenant\Document`) — tenant DB connection; columns: `id`, `title`, `description` (nullable), `file_path`, `file_name`, `file_size`, `mime_type`, `uploaded_by_user_id`, `uploaded_by_name`, timestamps
  - `DocumentRepository` — `paginate()` returns a `LengthAwarePaginator<DocumentRepositoryData>` via lazy `->through()`; `list()`, `find()`, `create()`, `delete()`
  - `DocumentService` — `paginate()`, `list()`, `find()`, `store()` (streams file via `putFileAs()`), `downloadResponse()` (signed URL redirect for S3/R2; streamed response for local disk), `delete()` (removes file then DB record), `getFilePath()`
  - `StoreDocumentRequest` — `title` (required, max:255), `description` (nullable, max:2000), `file` (required, max:20 MB)
  - `DocumentController` — web surface: `GET|POST /documents`, `GET /documents/{id}/download`, `DELETE /documents/{id}` (session auth, `ResolveWebTenantDatabase` middleware)
  - `DocumentApiController` — API surface: `GET|POST /api/v1/documents`, `GET /api/v1/documents/{id}`, `GET /api/v1/documents/{id}/download`, `DELETE /api/v1/documents/{id}` (Bearer + `X-App` + `X-Tenant` headers)
  - `DocumentRepositoryData` DTO (`app/Data/Repositories/Central/`) — maps all DB columns with `#[MapInputName]` for camelCase properties
  - `DocumentData` DTO (`app/Documents/Data/`) — strips internal `filePath` from API/controller responses
  - Tenant migration `2026_06_26_032822_create_documents_table.php` — driver-agnostic schema (works with both MySQL and PostgreSQL)
  - `TenantSettingsService::resolveDisk(int $tenantId): Filesystem` — new method that builds an ephemeral `Filesystem` from live tenant settings (`storage_driver` → S3, R2, or local); used by `DocumentService` and falls back to system defaults
  - `DocumentFactory` — `Database\Factories\Documents\DocumentFactory`

  **Frontend**

  - `resources/js/Pages/Documents/Index.vue` — upload form (title, description, file picker), paginated document table (file name with doc icon, title, size, uploaded by, upload date, Download + Delete actions), pagination controls; uses `TenantLayout` via `defineOptions({ layout: TenantLayout })`
  - `TenantLayout.vue` — Documents nav link added to the sidebar (between Report Queue and any future items)

  **Tests**

  - `DocumentWebTest` — 8 tests: guest redirect, page renders, prop shape, pagination (25 docs → 20/page), upload, title validation, file validation, delete
  - `DocumentApiTest` — 6 tests: unauthenticated 401, index JSON structure with pagination keys, store 201, store validation 422, show 200, destroy 200
  - `DocumentServiceTest` — 6 tests: paginate returns correct page/total, list, find, store (file on disk), downloadResponse (stream or redirect), delete (record + file removed)
  - `WithFakeTenantContext` trait (`tests/Concerns/`) — hot-swaps both `ResolveWebTenantDatabase` and `ResolveTenantDatabase` middleware bindings in tests to set `current_tenant` without reconfiguring the DB connection

  **Postman**

  - New **Documents (API)** folder with 5 requests: List Documents, Upload Document (saves `{{document_id}}`), Get Document, Download Document, Delete Document

- **Multi-driver tenant DB middleware** — both `ResolveWebTenantDatabase` and `ResolveTenantDatabase` now detect `DB_TENANT_DRIVER` (via `config('database.connections.tenant.driver')`) and build the correct connection config at runtime.

  - **PostgreSQL** (`pgsql`): sets `schema: 'public'`, `sslmode: 'prefer'`, `charset: 'utf8'`; no `collation`/`strict`/`engine` keys; default port falls back to `5432`
  - **MySQL** (`mysql`): retains existing `charset: 'utf8mb4'`, `collation: 'utf8mb4_unicode_ci'`, `strict: true`, `engine: null`; default port falls back to `3306`
  - Read replica (`write`/`read` split) and separate replica credentials work for both drivers
  - Fixes an `Internal Server Error` where environments using PostgreSQL for tenant databases received a driver mismatch because the middleware previously hardcoded `driver: 'mysql'`

  **Tests**

  - `ResolveWebTenantDatabaseMiddlewareTest` — new file with 9 tests: 503 when app not configured, 403 when no session tenant, 403 when user lacks access, 503 for maintenance, MySQL connection config, PostgreSQL connection config, PostgreSQL read/write split, MySQL read/write split, request attributes set correctly
  - `ResolveTenantDatabaseMiddlewareTest` — 4 new tests: PostgreSQL connection config, PostgreSQL read/write split, MySQL default port 3306, existing connection test updated to explicitly set `mysql` driver

---

## [2.22.0] — 2026-06-26

### Added

- **Tenant switcher — Phase 6.7** — users assigned to more than one tenant under the Tenant app can switch between tenants from a dropdown in the `TenantLayout.vue` sidebar without logging out or returning to the app picker.

  **Backend**

  - `ResolveWebTenantDatabase` middleware — session-based tenant resolver for all web tenant routes; reads the active tenant slug from `session('tenant_app_current_tenant')`, falls back to the user's `is_default` tenant on first visit, verifies access, wires the per-request `tenant` DB connection, and sets `current_app` / `current_tenant` on request attributes
  - `TenantSwitcherService` — `getTenantsForUser()` returns all accessible (non-maintenance) tenants for a user+app pair with an `isCurrent` flag; `initializeForUser()` seeds the session with the default tenant on first visit; `switchTenant()` validates access and updates the session key
  - `TenantSwitcherController` — handles `POST /tenant/switch` (web); validates via `SwitchTenantRequest`, delegates to service, redirects to `/tenant`
  - `TenantSwitcherApiController` — handles `GET /api/v1/tenant/tenants` (list) and `POST /api/v1/tenant/switch` (switch) for API consumers
  - `UserAppRepository` — two new methods: `getTenantsForUserAndApp(int $userId, string $appSlug)` returns a `Collection<TenantRepositoryData>` excluding maintenance tenants; `getDefaultTenantSlugForUserAndApp()` returns the default tenant slug for session initialisation
  - `TenantData` DTO (`app/Tenant/Data/`) — `id`, `name`, `slug`, `isCurrent`
  - `HandleInertiaRequests` — shares `availableTenants` prop (array of `{id, name, slug, isCurrent}`) for authenticated tenant-portal pages when the user has more than one accessible tenant; `null` otherwise
  - `WebAppPickerController::select()` — calls `TenantSwitcherService::initializeForUser()` when the Tenant app is selected so the session is seeded before the first page load
  - All web tenant routes (`/tenant`, `/tenant/reports`, `POST /tenant/switch`) now go through `ResolveWebTenantDatabase`

  **Frontend**

  - `TenantSwitcher.vue` (`resources/js/Pages/Partials/`) — dropdown rendered in the sidebar header below the tenant name; shows all accessible tenants; current tenant marked with a checkmark; switching calls `router.post('/tenant/switch', { tenant_slug })` so the dropdown closes before the Inertia visit fires (avoids the "form not connected" browser error)
  - `TenantLayout.vue` redesigned — tenant switcher moved to the header section directly below the tenant name; bottom of sidebar now shows avatar / name / email as a profile link with a separate logout icon button, matching the admin layout pattern

  **Tests**

  - `TenantSwitcherServiceTest` — 7 PHPUnit tests (list, maintenance filter, session init, no-overwrite, switch, unauthorised, current-flag)
  - `TenantSwitcherWebTest` — 3 PHPUnit tests (unauthenticated redirect, valid switch, unauthorised tenant error, missing slug validation)
  - `TenantSwitcherApiTest` — 4 PHPUnit tests (unauthenticated, list, maintenance filter, switch, forbidden)
  - `TenantLayout.test.js` — 16 Vitest tests (tenant name/slug, nav links, active highlighting, profile link, user name/email, avatar, profile picture, logout button, TenantSwitcher prop)
  - `TenantSwitcher.test.js` — 11 Vitest tests (hidden with ≤1 tenant, toggle, dropdown list, checkmark, switch post, backdrop close, re-toggle)

  **Postman**

  - New **Tenant Switcher (API)** folder with `GET /api/v1/tenant/tenants` (list) and `POST /api/v1/tenant/switch` (switch) — 200 OK, 403 Forbidden, and 422 Unprocessable example responses

---

## [2.21.0] — 2026-06-26

### Changed

- **Tenant migration clean-up — Phase 6.1** — the `database/migrations/tenant/` directory has been reset to a clean baseline ahead of the Phase 6 Documents module:

  **Removed migrations**
  - `2026_06_19_104351_create_companies_table.php`
  - `2026_06_19_104352_create_properties_table.php`
  - `2026_06_19_104354_create_floors_table.php`
  - `2026_06_19_104355_create_units_table.php`
  - `2026_06_19_104356_create_leases_table.php`
  - `2026_06_19_104357_create_lease_documents_table.php`
  - `2026_06_19_104358_create_lease_renewals_table.php`

  These were placeholder domain migrations from an earlier proof-of-concept; none were referenced by any model, repository, or service.

  **Retained migration**
  - `2026_06_20_043736_create_report_subscriptions_table.php` — actively used by the Phase 4 scheduled-report subscription system.

  **Schema reset**
  - `php artisan tenant:migrate --fresh` was run against all tenant databases; each tenant DB now contains only the `migrations` table and `report_subscriptions`.

---

## [2.20.0] — 2026-06-26

### Added

- **Tenant creation improvements** — three enhancements to the "Add Tenant" flow that make provisioning faster and more reliable:

  **Nullable database credentials**
  - `db_host`, `db_name`, `db_username`, and `db_password` on the `tenants` table are now nullable; previously they were `NOT NULL`, causing a `SQLSTATE[23502]` error when submitting the Add Tenant form with the Database Connection section left blank
  - Migration: `2026_06_25_231939_make_tenant_db_fields_nullable`

  **Default connection fallback**
  - When the Database Connection fields are left empty, `TenantManagementService::create()` fills them from the `tenant` database connection config (`DB_TENANT_HOST`, `DB_TENANT_PORT`, `DB_TENANT_USERNAME`, `DB_TENANT_PASSWORD`, falling back to the main `DB_*` env vars)
  - `db_name` is auto-generated from the tenant slug — e.g. slug `acme-corp` → database `tenant_acme_corp` — so the migration command creates an isolated database rather than connecting to the central database
  - Logic lives in a private `applyDefaultConnection(CreateTenantData): CreateTenantData` helper on `TenantManagementService`

  **Auto-assign admin users**
  - After a new tenant is created, `TenantRepository::assignAdminUsersToTenant(int $tenantId)` automatically inserts a `user_app_tenants` row for every user who holds `role = admin` on any app
  - `is_default` is set to `true` only when the admin has no other tenant assignments on that app yet
  - Existing assignments are skipped (idempotent); the call happens before `tenant:migrate` runs

  **Tests** (2 new `TenantManagementServiceTest` cases)
  - `test_create_uses_default_connection_when_db_host_is_empty` — asserts `db_host` and `db_name` are populated from config when left blank
  - `test_create_assigns_admin_users_to_new_tenant` — asserts a `user_app_tenants` row is created for the admin user after tenant creation

---

## [2.19.0] — 2026-06-26

### Added

- **App tour system** — every page now has a guided tour that auto-starts on first visit and can be retriggered at any time via a floating `?` button:

  **Core infrastructure**
  - `resources/js/composables/useTour.js` — composable wrapping [driver.js](https://driverjs.com/); accepts a `tourName` (used as the `localStorage` key `tour_seen_{name}`) and a `steps` array; auto-starts the tour 600 ms after mount if not yet seen; exposes `startTour()` for manual retrigger; guards against `localStorage` being undefined in SSR/jsdom environments
  - `resources/js/Pages/Partials/TourButton.vue` — floating `?` button rendered via `<Teleport to="body">` (fixed bottom-right, always above all layouts); emits `click` to call `startTour()`; contains all driver.js dark-theme CSS overrides (`.app-tour-popover`) targeting the slate-950 UI palette with correct arrow colours for all four popover sides
  - `driver.js` installed as an npm dependency
  - `resources/js/test-setup.js` — global Vitest setup file that mocks `driver.js` so CSS imports and DOM APIs don't break jsdom; registered in `vite.config.js` as `setupFiles`; `css: false` added to the test section to suppress CSS processing in the test environment

  **Pages covered** (tour key → steps)
  - `admin-dashboard` — System Overview, Tenant Health, Pending Invitations (conditional — step included only when `pendingUsers.length > 0`), Unresolved Errors
  - `admin-tenants` — Tenant Management header, Summary Stats, Tenant Table
  - `admin-apps` — Apps Table
  - `admin-users` — Users header, Users Table
  - `admin-settings` — Settings Tabs, Settings Panel
  - `admin-tenant-settings` — Settings Tabs, Settings Content
  - `admin-tenant-users` — Users header, Users Table
  - `admin-tenant-report-queue` — Report Stats, Report Table
  - `admin-tenant-errors` — Error Stats, Filters, Error Table
  - `tenant-dashboard` — Welcome, App Grid, Recent Activity
  - `tenant-report-queue` — Report Queue header, Stats, Report Table
  - `profile` — Display Name, Profile Picture, Change Password

  **New files**
  - `resources/js/composables/useTour.js`
  - `resources/js/Pages/Partials/TourButton.vue`
  - `resources/js/test-setup.js`

  **Modified files**
  - `vite.config.js` — added `css: false` and `setupFiles: ['resources/js/test-setup.js']` to the `test` block
  - All 12 page components above — added `useTour` composable call, `TourButton` component, and `id="tour-*"` attributes on tour target elements

  **Test infrastructure**
  - All 217 existing Vitest tests continue to pass; driver.js is globally mocked so CSS imports and browser-only APIs don't break jsdom
  - `localStorage` guards added in `useTour.js` (`onMounted` and `onDestroyStarted` callbacks) to handle environments where `localStorage` is undefined

---

## [2.18.0] — 2026-06-26

### Added

- **Admin tenant logged-in users — Phase 5.10** — admins can now see how many users are currently online per tenant and force-logout individual users from the tenant users page:

  **Tenant list (`/admin/tenants`)**
  - Users column now shows two lines: total user count and a live "X online" indicator
  - Green dot + count when users have active sessions; muted grey "0 online" when none are logged in
  - Powered by `TenantRepository::loggedInUserCountByTenant()` — single join query across `user_app_tenants` and `personal_access_tokens`
  - Actions column refactored into two compact rows: navigation links (Edit, Settings, Users, Reports, Errors) on top; admin actions (Migrate, Activate/Deactivate, Maintenance, Delete) below — eliminates horizontal overflow

  **Tenant users page (`/admin/tenants/{tenant}/users`)**
  - New **Session** column with a pulsing green "Online" badge for users with at least one active Sanctum token; dash for users with no active session
  - Header subtitle shows "X online" count alongside total user count
  - New **Force Logout** button (red, appears only for online users) — revokes all tokens for that user immediately and redirects with a flash success message

  **New endpoints**
  - Web: `DELETE /admin/tenants/{tenant}/users/{user}/session` (`admin.tenants.users.forceLogout`)
  - API: `DELETE /api/v1/admin/tenants/{tenant}/users/{user}/session` (`api.tenants.users.forceLogout`)
  - Both return a success message; the web endpoint redirects back to the tenant users page

  **New repository methods**
  - `TenantRepository::loggedInUserCountByTenant()` — map of `tenant_id → online user count`
  - `TenantRepository::loggedInUserIdsForTenant(int $tenantId)` — IDs of users with active tokens for a given tenant
  - `UserRepository::revokeTokensForUser(int $userId)` — single-user token revocation (complements the existing bulk `revokeTokensForUsers()`)
  - `UserRepository::hasActiveToken(int $userId): bool`

  **DTO changes**
  - `TenantData` — new `loggedInCount: int` field; serialises as `logged_in_count` in JSON
  - `UserData` (Admin) — new `isLoggedIn: bool` field; serialises as `is_logged_in` in JSON

  **Tests** (22 PHPUnit, 10 Vitest)
  - `TenantUsersWebTest` — 6 new tests: `is_logged_in` field presence, true/false token scenarios, force-logout revokes tokens, force-logout auth guard
  - `TenantUsersApiTest` — 6 new tests: `is_logged_in` in JSON structure, true/false token scenarios, force-logout 200 + token deletion, force-logout 401
  - `TenantManagementWebTest` — `logged_in_count` added to payload fields assertion; new `test_logged_in_count_reflects_users_with_active_tokens` verifies count is 1 when one of two tenant users has an active token
  - `Index.test.js` — 4 tests for online display (total count, green badge, muted-zero badge); 4 tests for compact two-row actions layout; sample fixtures updated with `user_count` and `logged_in_count`

  **Postman**
  - "List Users in Tenant" response updated — `is_logged_in` added to both user objects in the example; description updated to mention live session status
  - New **Force Logout User** request in "Tenant Users (API)" — `DELETE /api/v1/admin/tenants/:tenant_id/users/:user_id/session`; 200, 401, and 404 example responses documented

---

## [2.17.0] — 2026-06-26

### Added

- **Tenant maintenance mode — Phase 5.9** — admin can put any individual tenant or all tenants simultaneously into maintenance mode from `/admin/tenants`:

  **Per-tenant toggle**
  - Each tenant row gains a "Maintenance" / "End Maintenance" button; confirmation dialog prevents accidental clicks
  - Web: `PATCH /admin/tenants/{tenant}/maintenance` — `SetTenantMaintenanceRequest` validates `is_maintenance: boolean`
  - API: `PATCH /api/v1/admin/tenants/{tenant}/maintenance` — same validation; returns `{ message }` 200

  **Bulk toggle**
  - "Maintenance: All On" and "Maintenance: All Off" header buttons apply state to every tenant at once
  - Web: `PATCH /admin/tenants/maintenance/all`
  - API: `PATCH /api/v1/admin/tenants/maintenance/all`

  **Enforcement (three layers)**
  - **Token revocation** — enabling maintenance immediately calls `revokeTokensForUsers()` on all users assigned to that tenant, forcing logout of every active session
  - **503 middleware** — `ResolveTenantDatabase` returns `HTTP 503 Service Unavailable` for any API request whose resolved tenant has `is_maintenance = true`
  - **App picker filter** — `AppService::loadApps()` filters out maintenance tenants so they are invisible in the tenant picker post-login

  **Summary cards**
  - "In Maintenance" count card added to both `/admin/tenants` (5-card grid) and the admin dashboard health summary bar

  **Schema**
  - `database/migrations/central/2026_06_25_141540_add_is_maintenance_to_tenants_table.php` — adds `is_maintenance boolean default false` to the `tenants` table
  - `TenantRepositoryData`, `TenantData`, `TenantHealthData`, and `TenantHealthSummaryData` all expose the new `isMaintenance` field

  **Tests**
  - 9 new/updated backend test files: `TenantManagementWebTest`, `TenantManagementApiTest`, `TenantManagementServiceTest`, `TenantHealthWebTest`, `TenantHealthServiceTest`, `DashboardWebTest`, `DashboardApiTest`, `ResolveTenantDatabaseMiddlewareTest`, `AppServiceTest`
  - 11 new frontend Vitest cases in `resources/js/Pages/Admin/Tenants/Index.test.js`

---

## [2.16.0] — 2026-06-25

### Added

- **Live admin dashboard — Phase 5.8** — every widget on `Admin/Index.vue` now displays real data fetched through a strict Controller → Service → Repository → DTO pipeline:

  **Stat cards**
  - `total_users` = `active_users` + `pending_invitation_users`; `active_sso_sessions` counts live Sanctum `personal_access_tokens` rows
  - Served by `DashboardService::getStats()` → `DashboardStatsData`

  **Tenant health summary bar**
  - Proportional bar + three clickable status badges (Healthy / Warning / Critical); clicking a badge navigates to `/admin/tenants?health={status}`, filtering the tenants table in-page via a computed ref; an `×` chip dismisses the filter
  - Data from `DashboardService::getHealthSummary()` → `TenantHealthSummaryData`

  **Pending invitations widget**
  - Collapsible list of users with `invitation_token` set and `is_active = false`; shows name, email, and days-since-invited
  - **Resend Invitation action** — `POST /admin/users/{user}/resend-invitation` generates a fresh 64-char token, stamps `invitation_sent_at`, and re-queues `UserInvitationMail`; matching API route `POST /api/v1/admin/users/{user}/resend-invitation` returns `{ message }` 200
  - Data from `UserManagementService::pendingUsers()` and `::resendInvitation()`

  **Recent users table**
  - Latest 5 users by `created_at` desc; Active / Pending Invitation badge; Edit link deep-links to `/admin/users?edit={id}`, which `onMounted` reads to open the edit modal directly
  - Data from `UserManagementService::recentUsers(int $limit = 5)`

  **Unresolved error log summary**
  - Collapsible; header shows total + per-severity pills (critical / error / warning); per-tenant table with counts and "View errors →" links to `/admin/tenants/{id}/errors`
  - Single-query aggregation via `TenantErrorLogRepository::countUnresolvedByTenant()` (DB join + group by tenant_id × severity); `TenantErrorLogService::getUnresolvedSummary()` → `UnresolvedErrorSummaryData` + `UnresolvedErrorTenantData[]`

  **Report queue health widget**
  - Collapsible; failed count rendered in red; per-tenant table with pending / processing / failed columns and "View reports →" links
  - Single-query aggregation via `ReportRepository::countQueueStatusByTenant()`; `DashboardService::getReportQueueSummary()` → `ReportQueueSummaryData` + `ReportQueueTenantData[]`; by-tenant list sorted by total (desc)

  **Tenant migration compliance widget**
  - Collapsible; shows up-to-date vs behind counts; behind tenants listed with applied/available/behind-by columns and a "Run migrations" button that POSTs to the existing `admin.tenants.migrate` route
  - Available migration count from `TenantRepository::countAvailableMigrations()` (`File::files(database_path('migrations/tenant'))`); `DashboardService::getMigrationComplianceSummary()` → `MigrationComplianceSummaryData` + `MigrationComplianceTenantData[]`

  **Quick actions**
  - `+ Invite User` header button is now `<Link href="/admin/users?invite=1">`; `Admin/Users/Index.vue` `onMounted` checks `?invite` and calls `openInvite()`, then strips the param
  - `+ Add Tenant` links to `/admin/tenants?add=1`; `Admin/Tenants/Index.vue` `onMounted` checks `?add` and calls `openCreate()`
  - `Settings` shortcut links to `/admin/settings`

  **New DTOs** (`app/Admin/Data/`)
  - `DashboardStatsData` — six integer counters
  - `UnresolvedErrorSummaryData` / `UnresolvedErrorTenantData`
  - `ReportQueueSummaryData` / `ReportQueueTenantData`
  - `MigrationComplianceSummaryData` / `MigrationComplianceTenantData`

  **Repository methods added**
  - `UserRepository::countSsoSessions()` — `PersonalAccessToken::count()`
  - `UserRepository::refreshInvitationToken(int $id)` — new token + `invitation_sent_at`
  - `TenantErrorLogRepository::countUnresolvedBySeverity()` — `array<string, int>`
  - `TenantErrorLogRepository::countUnresolvedByTenant()` — join + group-by Collection
  - `ReportRepository::countQueueStatusByTenant()` — join + group-by Collection
  - `TenantRepository::countAvailableMigrations()` — filesystem count
  - `UserRepository::countPendingInvitation()` — `whereNotNull('invitation_token')`

  **API parity**
  - `GET /api/v1/admin/dashboard` (`admin.api.dashboard`) — returns the same seven keys as the Inertia page (`data`, `health_summary`, `recent_users`, `pending_users`, `unresolved_errors`, `report_queue`, `migration_compliance`); protected by `auth:sanctum`

  **Tests** (53 PHPUnit, 31 Vitest)
  - `DashboardServiceTest` — 18 tests: return types, total = active + pending, active/pending/app/tenant/tenant-inactive counts, SSO session increments and multi-user totals, pending-invitation excludes no-token users, total excludes no-token users, report queue pending/processing/failed/success-excluded/by-tenant-sorted, migration compliance type/arithmetic/behind-have-fewer/count-matches-array
  - `DashboardWebTest` — 17 tests: auth guard, all 7 props present, unresolved errors keys, pending users includes invited/excludes no-token/excludes active, recent users ≤ 5/newest-first, stats keys, health summary keys/total=sum, migration compliance keys/arithmetic, report queue keys, unresolved errors increments/excludes resolved, SSO sessions increment
  - `DashboardApiTest` — 18 tests: 401, full JSON structure, stat integer types, health summary integers/total=sum, pending users includes/excludes correctly, recent users ≤ 5/newest-first, unresolved errors integers/increments/excludes resolved, report queue integers, migration compliance integers/arithmetic
  - `Admin/Index.test.js` — 31 Vitest tests covering all widgets, badge links, empty states, `migrationCompliance` rendering, quick-action hrefs

  **Postman**
  - New **Admin Dashboard (API)** folder with `GET /api/v1/admin/dashboard` — full 200 response example with all seven keys; 401 example
  - **Resend Invitation** added to **User Management (API)** — `POST /api/v1/admin/users/:id/resend-invitation`; 200 and 404 examples

---

## [2.15.0] — 2026-06-25

### Added

- **Persistent admin tenant layout — Phase 5.7** — admin users navigating between tenant-specific pages (Settings, Users, Reports, Errors) no longer need to return to the tenants list to switch pages; a fixed sub-navigation bar persists across all five tenant pages:
  - **`AdminTenantLayout.vue`** (`resources/js/Layouts/`) — Inertia persistent layout applied via `defineOptions({ layout: AdminTenantLayout })` on all five admin tenant pages; reads `tenant` from `usePage().props` (already a shared Inertia prop) without extra prop drilling
  - **Sticky sub-nav bar** — sits below the main admin sidebar; shows a "← Tenants" back-link, the current tenant name, and four tab-style links (Settings, Users, Reports, Errors); active tab highlighted per-page with colour coding (violet / slate / blue / red)
  - **Active route detection** — `page.url.split('?')[0]` used to strip query strings before comparison; `startsWith(href + '/')` used so nested routes (e.g. `/errors/abc-123`) correctly activate the parent Errors tab
  - **Sidebar HTML eliminated** — the `<aside>` block (220+ lines) that was duplicated in all five pages is removed; now lives exclusively in `AdminTenantLayout.vue`
  - **Pages updated** — `Admin/Tenants/Settings.vue`, `Admin/Tenants/Users.vue`, `Admin/Tenants/Errors.vue`, `Admin/Tenants/ErrorDetail.vue`, `Admin/Tenants/ReportQueue.vue`
  - **13 Vitest tests** — `AdminTenantLayout.test.js`: brand, main nav links, slot content, tenant sub-nav link hrefs, tenant name, active-state highlighting per page, error-detail sub-route activation, sign-out, no sub-nav when tenant is null, query-string stripping

---

## [2.14.0] — 2026-06-25

### Added

- **Tenant report queue — Phase 5.7** — both tenant users and admin users can view all report jobs for a tenant with live status updates:

  **Tenant view** (`/tenant/reports`)
  - `TenantReportQueueController` + `TenantReportQueueApiController` (in `app/Reports/`) — reads `current_tenant` from `$request->attributes` (set by `ResolveTenantDatabase` middleware); 404 if no tenant context; renders `Tenant/ReportQueue` Inertia page or returns paginated JSON
  - `resources/js/Pages/Tenant/ReportQueue.vue` — stat cards (Total / Pending / Processing / Failed), status and format badges, table with per-row download link for completed reports, "Live" indicator when active jobs exist, pagination; `usePoll(4000, { only: ['reports'] }, { autoStart: true })` auto-refreshes every 4 s while open
  - Uses `TenantLayout` persistent layout so the Reports nav link stays active during polling
  - Web route: `GET /tenant/reports` (`tenant.reports`) — behind `auth` + `ResolveTenantDatabase`
  - API route: `GET /api/v1/tenant/reports` (`tenant.reports.index`) — behind `auth:sanctum` + `ResolveTenantDatabase`
  - **17 Vitest tests** — status/format badges, download link, Live indicator, empty state, pagination, polling call

  **Admin view** (`/admin/tenants/{tenant}/reports`)
  - `Admin\Http\Controllers\TenantReportQueueController` + `TenantReportQueueApiController` — uses `Tenant $tenant` route model binding (no middleware attribute needed)
  - `resources/js/Pages/Admin/Tenants/ReportQueue.vue` — same stat card + table layout; no download link (admin read-only); `usePoll(4000, { only: ['reports'] })`; uses `AdminTenantLayout` persistent layout
  - **"Reports" button** added to each row in `Admin/Tenants/Index.vue`
  - Web route: `GET /admin/tenants/{tenant}/reports` (`admin.tenants.reports`)
  - API route: `GET /api/v1/admin/tenants/{tenant}/reports` (`admin.api.tenants.reports`)
  - **14 Vitest tests** — `Admin/Tenants/ReportQueue.test.js`

  **Shared infrastructure**
  - `ReportRepository::listForTenant(int $tenantId, int $perPage = 25)` — paginated, eager-loads `user:id,name,email`, ordered latest-first
  - **11 new PHPUnit tests** — `TenantReportQueueWebTest` (6), `TenantReportQueueApiTest` (5) in `app/Reports/`; `Admin\Tests\TenantReportQueueWebTest` (6), `Admin\Tests\TenantReportQueueApiTest` (4); `ReportRepositoryTenantTest` (5) in `tests/Feature/Repositories/Central/`
  - **Postman** — "Tenant Report Queue (API)" folder added with `GET /api/v1/tenant/reports`; "List Report Jobs (Admin View)" added to "Tenant Management (API)" folder

---

## [2.13.0] — 2026-06-25

### Added

- **Per-tenant Redis connection override — Phase 5.7** — report jobs for a tenant can now be routed to a dedicated Redis queue connection (e.g. a separate Redis server) in addition to the existing queue name and timeout overrides:
  - **`report_connection` setting key** — added to `ALLOWED_KEYS` in `TenantSettingsService`, `TenantSettingsData` DTO (`public ?string $reportConnection`), and `UpdateTenantSettingsRequest` validation (`nullable|string|max:255`)
  - **`GenerateReportJob`** — constructor now accepts `string $queue = 'reports'`, `?int $timeout = null`, `?string $connection = null`; calls `$this->onQueue($queue)`, conditionally sets `$this->timeout`, conditionally calls `$this->onConnection($connection)` — all three are applied before the job is pushed
  - **`GenerateReportBatchJob`** — `dispatch()` accepts and passes all three parameters to each `GenerateReportJob`; calls `$batch->onConnection($connection)` to route the batch itself
  - **`ReportController::resolveReportConfig()`** — private helper that reads `current_tenant` from `$request->attributes`, calls `TenantSettingsService::getSettings()`, and returns a `[queue, timeout, connection]` tuple; both `store()` and `batch()` destructure the tuple and pass all three to dispatch
  - **Report Server tab** — dedicated fifth tab in `Admin/Tenants/Settings.vue` (separate from Report PDF); Queue name input, Timeout input, Redis Connection `<select>` dropdown populated from `redisConnections` prop
  - **`redisConnections` prop** — `TenantSettingsController::index()` filters `config('queue.connections')` to redis-driver entries and passes `array_values(array_keys(...))` as the `redisConnections` Inertia prop; `TenantSettingsApiController::index()` returns `redis_connections` in the JSON envelope
  - **2 new PHPUnit tests** — `test_redis_connections_prop_contains_only_redis_driver_keys` (web), `test_report_server_settings_can_be_saved` (web), `test_report_connection_can_be_saved_via_api` (API)
  - **Postman** — "Update Report Server Settings" request added to "Tenant Settings (API)"; "Update Report PDF Settings" body trimmed to PDF-only fields

---

## [2.12.0] — 2026-06-25

### Added

- **Tenant error logs — Phase 5.7b** — every unhandled exception inside a tenant request context is automatically captured and stored in the central `tenant_error_logs` table with a unique support code:
  - **Automatic capture** — `reportable()` callback in `bootstrap/app.php` detects the `current_tenant` attribute set by `ResolveTenantDatabase` middleware; HTTP exceptions (401/403/404) are deliberately excluded
  - **Error code format** — `E-{SLUG}-{8-char-random}` e.g. `E-ACME-A3F9B12C`; uniqueness enforced in both generation loop and DB unique constraint
  - **Full context snapshot** — exception class, message, file, line, 30-frame stack trace, sanitized request params and headers (`password`, `token`, `secret`, `authorization` and similar keys redacted to `[REDACTED]`), user ID (nullable), IP, user agent, app slug, tenant slug
  - **Production masking** — in production (`app()->isProduction()`), the `renderable()` callback replaces the raw exception with `{"error_code":"E-ACME-...","message":"An unexpected error occurred. Quote the error code when contacting support."}` for API requests, or a minimal Blade error page (`resources/views/errors/tenant.blade.php`) for web requests; development environments retain default Laravel error rendering
  - **`TenantErrorContext`** — static per-request bridge between the `reportable()` and `renderable()` callbacks; `reportable()` stores the generated code, `renderable()` reads and clears it
  - **Admin UI** — `GET /admin/tenants/{tenant}/errors` lists all logs with severity filter and unresolved-only toggle; stat cards show total / unresolved / critical / error counts; each row links to the full detail page
  - **Detail page** — `GET /admin/tenants/{tenant}/errors/{error}` shows exception summary, request URL/method/params/headers, context block, and 30-frame stack trace; copy-to-clipboard button for the error code
  - **Lifecycle management** — mark resolved, reopen, delete (web + API); resolved entries are visually dimmed
  - **Support lookup** — `GET /api/v1/admin/errors/{errorCode}` finds any error log across all tenants by code — for support team use when a user quotes their error code
  - **`TenantErrorsController` + `TenantErrorsApiController`** — `index`, `show`, `resolve`, `unresolve`, `destroy`; `show` / `resolve` / `unresolve` / `destroy` enforce tenant ownership (returns 404 if tenant mismatch)
  - **`TenantErrorLogRepository`** — `create()`, `listForTenant()`, `findByCode()`, `findById()`, `resolve()`, `unresolve()`, `delete()`, `generateErrorCode()`, `sanitizeParams()`, `sanitizeHeaders()`
  - **24 new PHPUnit tests** across `TenantErrorLogServiceTest` (16) and `TenantErrorLogWebTest` (8)
  - **Postman** — "Tenant Error Logs (API)" folder added with 7 documented requests including the cross-tenant code lookup

---

## [2.11.0] — 2026-06-25

### Added

- **Per-tenant settings — Phase 5.7** — admins can configure tenant-specific overrides for email, storage, report PDF, and branding; unset fields transparently fall back to system settings at runtime:
  - **`tenant_settings` table** — `(tenant_id, key, value)` with a unique constraint on `(tenant_id, key)`; setting null/empty string for a key deletes its override row, restoring system-default behaviour
  - **`TenantSettingsData` DTO** — 40 nullable fields mirroring the tenant-overridable subset of `SystemSettingsData`, plus `effectiveEmailDriver` and `effectiveStorageDriver` (tenant override ?? system setting)
  - **`TenantSettingsService`** — `getSettings()→TenantSettingsData`, `updateSettings()`, `uploadLogo()`, `deleteLogo()`, `resolveMailConfig()`, `resolveS3Config()`; the resolve methods return runtime-ready config arrays that merge tenant overrides with system defaults for use in queue jobs and mailers
  - **Email overrides** — driver (`smtp`, `postmark`, `mailgun`, `ses`) + all per-driver credentials; a tenant with no email override uses the system driver and credentials transparently
  - **Storage overrides** — driver (`s3`, `r2`) + credentials; tenant-scoped S3 and R2 buckets for file uploads and report delivery
  - **Report PDF settings** — header text and footer text (both support HTML from the TipTap rich-text editor); logo upload stored under `public/tenant-logos/{tenant_id}/`; queue name and timeout override
  - **Report PDF live preview** — Settings.vue Report PDF tab renders a mock A4 page in real time as the admin types; logo, header, and footer update instantly via computed refs + `v-html`
  - **Branding overrides** — app name, support email, support URL
  - **`UpdateTenantSettingsRequest`** — 30+ nullable validation rules; logo upload validated by `UploadTenantLogoRequest` (image, max 2 MB, mime whitelist)
  - **`TenantSettingsController` + `TenantSettingsApiController`** — `index`, `update`, `uploadLogo`, `deleteLogo`
  - **Routes** — `GET|PUT /admin/tenants/{tenant}/settings`, `POST|DELETE /admin/tenants/{tenant}/settings/logo` (web + API parity)
  - **27 new PHPUnit tests** across `TenantSettingsWebTest`, `TenantSettingsApiTest`, `TenantSettingsServiceTest`
  - **Postman** — "Tenant Settings (API)" folder with 8 documented requests

- **Tenant user management — Phase 5.7** — per-tenant user list accessible directly from the tenant management table:
  - **`GET /admin/tenants/{tenant}/users`** and **`GET /api/v1/admin/tenants/{tenant}/users`** — returns all users with an entry in `user_app_tenants` for the given tenant, with full app permissions
  - **`UserRepository::listForTenant(int $tenantId)`** — scoped query filtering by `user_app_tenants.tenant_id`
  - **`TenantUsersController` + `TenantUsersApiController`** — web returns `Admin/Tenants/Users` Inertia page; API returns `{ data: [...] }`
  - **`Admin/Tenants/Users.vue`** — user table with status badges (Active / Invited / Inactive), app permission chips, and an inline edit modal (reuses the same app-permission flow as global user management)
  - Tenant Index row now has **Settings**, **Users**, and **Errors** action buttons per row
  - **Postman** — "Tenant Users (API)" folder with 1 documented request

---

## [2.10.0] — 2026-06-24

### Added

- **Tenant management — Phase 5.6** — full admin CRUD for tenants at `/admin/tenants` and `GET|POST|PUT|PATCH|POST|DELETE /api/v1/admin/tenants/*`:
  - **Create tenant** — admin fills name, slug, and optional DB credentials; on create, `php artisan tenant:migrate` runs automatically to provision the tenant database
  - **Edit tenant** — name, slug, all DB connection fields (host, port, name, username, password), and read replica settings are editable; leaving password blank preserves the existing encrypted value
  - **Toggle `is_active`** — deactivating a tenant immediately deletes all Sanctum tokens for every user assigned to that tenant (force-logout); activating restores access without token side effects
  - **Run migrations** — per-tenant "Migrate" button and global "Run All Migrations" button trigger `tenant:migrate` synchronously and redirect with a success flash
  - **Delete tenant** — drops the tenant database (best-effort; connection failures do not block deletion), revokes user tokens, and removes all central records; `user_app_tenants` and `tenant_migration_versions` cascade automatically via FK constraints
  - **`TenantData` DTO** — service-layer DTO combining DB credential fields (`dbHost`, `dbPort`, `dbName`, `dbUsername`, `isPasswordSet`, `hasReadReplica`) with health metrics (`healthStatus`, `userCount`, `migrationCount`, `pendingReports`, `failedReports`) — the `/admin/tenants` page is now the combined management + health dashboard
  - **`TenantManagementService`** — `list()`, `create()`, `update()`, `setActive()`, `delete()`, `runMigrations()`; `setActive(false)` and `delete()` both call `UserRepository::revokeTokensForUsers()` before mutating state
  - **`TenantRepository`** — extended with `find()`, `create()`, `update()`, `setActive()`, `delete()`, `dropDatabase()`, `getUserIdsForTenant()`
  - **`UserRepository::revokeTokensForUsers()`** — bulk Sanctum token revocation for a collection of user IDs
  - **Validation** — `CreateTenantRequest` and `UpdateTenantRequest` enforce slug format (`/^[a-z0-9-]+$/`) and uniqueness (update scoped to self via `Rule::unique()->ignore()`)
  - **Vue page** — `Admin/Tenants/Index.vue` rewritten; health summary cards retained; table gains Edit, Migrate, Activate/Deactivate, and Delete action buttons per row; Add Tenant and Run All Migrations added to the header; Edit/Create modals include collapsible read replica section
  - **28 new PHPUnit tests** across `TenantManagementWebTest`, `TenantManagementApiTest`, and `TenantManagementServiceTest`
  - **Postman** — "Tenant Management (API)" folder added with 7 documented requests; "Create Tenant" saves `{{tenant_id}}` automatically

---

## [2.9.0] — 2026-06-24

### Added

- **Architecture standards enforced via CLAUDE.md** — project-specific rules codified so every future Claude Code session follows the same layered architecture automatically:
  - **Repository pattern** — all Eloquent access must live in `App\Repositories\Central\`; repositories return DTOs, never models; parameters accept primitives or DTOs, never Eloquent instances
  - **DTO contracts** — repository DTOs in `App\Data\Repositories\Central\`; service/module DTOs in `App\{Module}\Data\`; all properties camelCase (global `SnakeCaseMapper` handles DB ↔ JSON); `#[MapInputName]` used where column name differs
  - **Service DTO return rule** — all public service methods must return DTOs or typed Collections of DTOs, never raw arrays or Eloquent models
  - **Validation** — all request validation in dedicated `FormRequest` classes; no inline `$request->validate()`
  - **API parity** — every web route must have a matching REST API endpoint (`{Feature}ApiController`, `auth:sanctum`, `{ data: ... }` responses)
  - **Module structure** — `app/{Module}/Data/`, `Http/Controllers/`, `Http/Requests/`, `Routes/`, `Services/`, `Tests/` — `app/Profile/` is the canonical template

- **Repository DTOs (`app/Data/Repositories/Central/`)** — full set of typed DTOs replacing raw Eloquent model returns:
  - `AppRepositoryData` — id, name, slug, description, isActive
  - `ReportRepositoryData` — id (`string`, UUID), userId, tenantId, type, format, delivery, status, parameters, filePath, errorMessage, batchId, startedAt, completedAt, createdAt
  - `TenantRepositoryData` — id, name, slug, isActive, dbHost, dbPort, dbName, dbUsername, dbPassword, hasReadReplica, migrationVersions (embedded)
  - `TenantMigrationVersionRepositoryData` — migration, batch, migratedAt
  - `UserRepositoryData` — id, name, email, profilePicture, isActive, invitationToken, invitationSentAt
  - `UserWithPermissionsRepositoryData` — id, name, email, isActive, invitationSentAt, profilePictureUrl, apps[]
  - `UserAppRepositoryData` — appId, appName, role, tenantIds[]

- **All repositories refactored** — every public method returns a DTO or typed Collection/Paginator:
  - `AppRepository` — `listOrdered()→Collection<AppRepositoryData>`, `update()→AppRepositoryData`; column-selection removed (fixed-shape DTO requires all columns)
  - `TenantRepository` — `allWithMigrationVersions()`, `listActive()`, `listActiveWithMigrationVersions()`, `listOrdered()` all return `Collection<TenantRepositoryData>`; migration versions embedded as `TenantMigrationVersionRepositoryData[]`
  - `ReportRepository` — `create()→ReportRepositoryData`, `listForUser()→LengthAwarePaginator<ReportRepositoryData>`; uses `->through()` to map paginator items to DTOs
  - `UserRepository` — `find()`, `listWithPermissions()`, `findWithPermissions()`, `findByInvitationToken()`, `createInvited()` all return DTOs; `updateProfile()` and `activateInvitation()` return void

- **Service DTO returns fixed**:
  - `SystemSettingsService::getSettings()` now returns `SystemSettingsData` (new DTO, 85 camelCase properties); `getMissingRequiredSettings()` now returns `MissingSystemSettingsData` (wraps `list<string>` of labels)
  - `UserManagementService::list()` return type changed from `array<int, UserData>` to `Collection<int, UserData>`
  - `HandleInertiaRequests` and `SystemSettingsApiController` updated to unwrap `.labels` for backward-compatible JSON shape

- **`GenerateReportJob` serialization fix** — replaced `SerializesModels` + `Report $report` constructor parameter with `string $reportId` (UUID); model reloaded fresh in `handle()` and `failed()`; eliminates model serialization in queue payload

- **`UserInvitationMail` serialization fix** — replaced `SerializesModels` + `User $user` with `UserRepositoryData $user`; token URL built from `$user->invitationToken` in constructor

- **Repository contract tests** (`tests/Feature/Repositories/Central/`) — four new test files verifying DTO return types end-to-end:
  - `AppRepositoryTest` — `listOrdered()` returns `Collection<AppRepositoryData>`, `update()` returns `AppRepositoryData` with updated values
  - `TenantRepositoryTest` — `listOrdered()` includes DB config fields and `hasReadReplica` flag, `allWithMigrationVersions()` embeds `TenantMigrationVersionRepositoryData[]`, `listActive()` filters inactive tenants and supports slug scoping
  - `ReportRepositoryTest` — `create()` returns `ReportRepositoryData` with UUID string `id`, `listForUser()` returns `LengthAwarePaginator<ReportRepositoryData>` scoped to user, respects `perPage`
  - `UserRepositoryTest` — `findWithPermissions()` returns `UserWithPermissionsRepositoryData` with embedded apps, `createInvited()` returns inactive DTO with token, `activateInvitation()` activates user and clears token

- **Service layer tests**:
  - `UserManagementServiceTest` — verifies `list()→Collection<UserData>`, `invite()→UserData`, `update()→UserData`, `acceptInvitation()→UserData`
  - `SystemSettingsServiceTest` — verifies `getSettings()→SystemSettingsData`, `getMissingRequiredSettings()→MissingSystemSettingsData`, `updateSettings()` persists and is reflected through the DTO

- **Bug fix — `route()` not defined** — `route()` (Ziggy) was used in Vue pages without being installed or wired up; replaced all four occurrences with inline URL strings:
  - `Admin/Users/Index.vue` — `route('admin.users.invite')` → `'/admin/users/invite'`; `route('admin.users.update', id)` → `` `/admin/users/${id}` ``
  - `Admin/Apps/Index.vue` — `route('admin.apps.update', id)` → `` `/admin/apps/${id}` ``
  - `Admin/Users/Accept.vue` — `route('invitation.accept.submit', {token})` → `` `/invitation/${token}` ``

---

## [2.8.0] — 2026-06-24

### Added

- **User management — Phase 5.5** — admin can invite users by email and edit user profiles + permissions over both the Inertia web interface and a versioned REST API:

  **Web**
  - `App\Admin\Http\Controllers\UserManagementController` — `GET /admin/users` renders `Admin/Users/Index` via Inertia with all users, apps, tenants, and roles as props; `POST /admin/users/invite` sends an invitation email and redirects; `PUT /admin/users/{user}` updates profile and permissions and redirects
  - `App\Admin\Http\Controllers\InvitationController` — `GET /invitation/{token}` renders `Admin/Users/Accept` for inactive users; `POST /invitation/{token}` accepts the invitation, sets name/password, activates the account, and redirects to login
  - `resources/js/Pages/Admin/Users/Index.vue` — user table with status badges (Active / Invited); Invite modal with name, email, and dynamic per-app / per-tenant permission selectors; Edit modal pre-filled from existing data with same permission selectors; Cancel reverts without page reload
  - `resources/js/Pages/Admin/Users/Accept.vue` — invitation acceptance form (name, password, password confirmation) rendered at `/invitation/{token}`
  - Users nav link wired in all admin sidebar pages

  **API**
  - `App\Admin\Http\Controllers\UserManagementApiController` — `GET /api/v1/admin/users` returns `{ "data": [...] }` with all users and their permissions; `POST /api/v1/admin/users/invite` creates an inactive user, assigns permissions, sends email, returns `{ "data": {...} }` with 201; `PUT /api/v1/admin/users/{user}` updates profile and permissions, returns `{ "data": {...} }`; all require Sanctum Bearer auth
  - `App\Admin\Data\InviteUserData` — name, email, apps (`DataCollection<UserAppPermissionData>`)
  - `App\Admin\Data\UpdateUserData` — name, email, apps (`DataCollection<UserAppPermissionData>`)
  - `App\Admin\Data\UserData` — id, name, email, isActive, invitationSentAt, profilePictureUrl, apps (`UserAppData[]`)
  - `App\Admin\Mail\UserInvitationMail` — queued mailable that accepts `UserRepositoryData` (no `SerializesModels`); acceptance URL built from `invitationToken`

  **Tests**
  - `App\Admin\Tests\UserManagementWebTest` — PHPUnit tests: list page, invite happy path, edit happy path, validation, invitation acceptance flow, duplicate email, inactive user guard
  - `App\Admin\Tests\UserManagementApiTest` — PHPUnit tests: 401, list structure, invite, update, validation, duplicate email, 404
  - `resources/js/Pages/Admin/Users/Index.test.js` — Vitest tests: table rendering, status badges, invite modal toggle, edit modal pre-fill, permission selectors

  **Postman**
  - New **User Management (API)** folder: `GET /api/v1/admin/users` (List Users), `POST /api/v1/admin/users/invite` (Invite User — saves `{{invited_user_id}}`), `PUT /api/v1/admin/users/:id` (Update User) with 200/201, 401, 404, and 422 example responses

---

## [2.7.0] — 2026-06-24

### Added

- **App management — Phase 5.4** — admin can view and edit app information (name, description) over both the Inertia web interface and a versioned REST API:

  **Web**
  - `App\Admin\Http\Controllers\AppManagementController` — `GET /admin/apps` renders `Admin/Apps/Index` via Inertia with all apps as props (id, name, slug, description, is_active, ordered by name); `PUT /admin/apps/{app}` saves name/description and redirects with a flash success message
  - `resources/js/Pages/Admin/Apps/Index.vue` — table listing all apps with inline edit rows; clicking Edit expands the row with pre-filled name and description inputs; Cancel reverts without a page reload
  - Apps nav link wired in all admin sidebar pages (Dashboard, Tenants, Settings)

  **API**
  - `App\Admin\Http\Controllers\AppManagementApiController` — `GET /api/v1/admin/apps` returns `{ "data": [...] }` with all apps; `PUT /api/v1/admin/apps/{app}` updates and returns `{ "data": {...} }` for the affected app; both require Sanctum Bearer auth
  - `App\Http\Requests\Admin\UpdateAppRequest` — validates `name` (required, max 255) and `description` (nullable, max 1000); shared by web and API controllers

  **Tests**
  - `App\Admin\Tests\AppManagementWebTest` — 12 PHPUnit tests: auth redirects, listing, payload fields, update happy path, clear description, name required, max-length validation, 404, slug/URL immutability
  - `App\Admin\Tests\AppManagementApiTest` — 10 PHPUnit tests: 401 on unauthenticated GET/PUT, list structure, update, clear description, validation errors, 404, slug/URL immutability
  - `resources/js/Pages/Admin/Apps/Index.test.js` — 12 Vitest tests: heading, row-per-app, name/slug display, null description fallback, status badges, Edit button count, empty state, edit form toggle, form pre-fill, cancel, missing-settings banner, active nav item

  **Postman**
  - New **App Management (API)** folder: `GET /api/v1/admin/apps` (List Apps) and `PUT /api/v1/admin/apps/:id` (Update App) with 200, 401, 404, and 422 example responses

---

## [2.6.0] — 2026-06-23

### Added

- **System settings — Phase 5.3** — full admin settings management across seven tabs, exposed over both the Inertia web interface and a versioned REST API:

  **Service & validation**
  - `App\Admin\Services\SystemSettingsService` — `getSettings()` (fetches all known keys in one query), `updateSettings(array)` (upserts via `SystemSetting::set()`), `getMissingRequiredSettings()` (returns labels for the three required keys: `email_driver`, `authentication_idle_time`, `storage_driver`)
  - `App\Http\Requests\Admin\UpdateSystemSettingsRequest` — validates all settings fields across all tabs (80+ rules); `authorize()` requires an authenticated user

  **Web controller**
  - `App\Admin\Http\Controllers\SystemSettingsController` — `GET /admin/settings` renders `Admin/Settings/Index` via Inertia with all settings as props; `PUT /admin/settings` saves and redirects with a flash success message

  **API controller**
  - `App\Admin\Http\Controllers\SystemSettingsApiController` — `GET /api/v1/admin/settings` returns `{ "data": {...}, "missing_required": [...] }`; `PUT /api/v1/admin/settings` saves and returns the same envelope; both endpoints require Sanctum Bearer auth

  **Missing-settings banner**
  - `HandleInertiaRequests::share()` — adds `missingRequiredSettings` as a lazy closure prop shared to every page; resolves to an array of setting labels that are unset; used by admin pages to display an amber banner linking to `/admin/settings`
  - Banner implemented on `Admin/Index.vue`, `Admin/Tenants/Index.vue`, and `Admin/Settings/Index.vue`

  **Settings tabs (7)**

  | Tab | Driver / options | Fields |
  |---|---|---|
  | **Email** | SMTP, Postmark, Mailgun, Amazon SES | Host, port, encryption, credentials, from address/name per driver |
  | **SMS** | Twilio, Vonage, Amazon SNS | Credentials + sender per driver; SNS sender ID capped at 11 chars |
  | **Push** | FCM, APNs, OneSignal | Firebase/APNS/OneSignal credentials; APNs environment (sandbox/production) |
  | **Storage** | Local, Amazon S3, Cloudflare R2, Google Cloud Storage, FTP, SFTP | Credentials + bucket/path per driver; GCS accepts service account JSON |
  | **Authentication** | — | Idle timeout (1–1440 min), max login attempts (1–100) |
  | **Security** | — | Password min length, require uppercase/digit/symbol (boolean), expiry days (0–365), 2FA (off/optional/required), session concurrency limit |
  | **Branding** | — | App name, support email + URL, logo URL, favicon URL (inline preview with error fallback) |

  **Email footer** (always visible, driver-independent)
  - `email_footer_signature` — rich-text HTML via TipTap editor (max 2000 chars); appended to all outbound emails; tenants can override
  - `email_footer_unsubscribe_url` — URL field for CAN-SPAM/GDPR compliance

  **TipTap rich text editor**
  - `resources/js/Pages/Admin/Settings/RichTextEditor.vue` — self-contained TipTap v3 editor component used for the email footer signature; toolbar: Bold, Italic, Underline, Bullet list, Ordered list, Link (URL prompt), Remove link, Clear formatting; restricted to email-safe marks (no headings, blockquotes, code blocks); emits `update:modelValue` as raw HTML; wired via `v-model` into `emailForm.email_footer_signature`
  - `@tiptap/vue-3`, `@tiptap/pm`, `@tiptap/starter-kit`, `@tiptap/extension-link`, `@tiptap/extension-underline` added as npm dependencies

  **Per-tab form isolation**
  - Seven separate `useForm` instances (`emailForm`, `smsForm`, `pushForm`, `storageForm`, `authForm`, `securityForm`, `brandingForm`) so saving one tab does not reset or dirty others
  - Each tab has its own save button; boolean security settings initialised with `=== '1' || === true` to handle string storage

  **Admin sidebar**
  - `Admin/Index.vue` and `Admin/Tenants/Index.vue` — "Settings" sidebar link updated to use a real Inertia `<Link>` pointing to `route('admin.settings')`

### Routes added

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `GET` | `/admin/settings` | Session | Show the settings page (Inertia) |
| `PUT` | `/admin/settings` | Session | Save settings (redirects with flash) |
| `GET` | `/api/v1/admin/settings` | Bearer | Retrieve all settings + missing required list |
| `PUT` | `/api/v1/admin/settings` | Bearer | Update settings, return updated data + missing required list |

### Tests

- **`app/Admin/Tests/SystemSettingsWebTest.php`** — 43 PHPUnit feature tests covering: auth guard, page rendering, saving each driver's settings (SMTP, Postmark, Mailgun, SES, Twilio, Vonage, SNS, FCM, APNs, OneSignal, S3, R2, GCS, Local, FTP, SFTP), all validation rules, boolean security settings, branding, email footer (plain text, HTML, clear), and the missing-required banner prop
- **`app/Admin/Tests/SystemSettingsApiTest.php`** — 33 PHPUnit feature tests covering: 401 on unauthenticated requests, JSON structure, each driver save, validation rejection, email footer (HTML, clear), and missing-required logic
- **`resources/js/Pages/Admin/Settings/RichTextEditor.test.js`** — 6 Vitest component tests: toolbar renders all buttons, `EditorContent` mounts, `update:modelValue` emitted on init, remove-link button conditional on active link state, `modelValue` prop passed to editor

---

## [2.5.0] — 2026-06-23

### Changed

- **`App\Auth\` module — refactored to match Profile module pattern** — `Actions/` and `Data/Core/` removed; replaced with `Services/` and a flat `Data/` directory, consistent with how `App\Profile\` is structured. All model access now lives exclusively in repositories:
  - `App\Auth\Actions\LoginAction` → `App\Auth\Services\AuthService` — `login(LoginCredentialsData): AuthTokenData`; delegates user lookup, password verification, and token creation to `UserRepository`; delegates app loading to `AppService`; no Eloquent imports remain
  - `App\Auth\Actions\LoadUserAppsAction` → `App\Auth\Services\AppService` — `loadApps(int $userId): DataCollection`; accepts a user ID instead of a `User` model; delegates all queries to `UserAppRepository`
  - `App\Repositories\Central\UserAppRepository` (new) — `getAppsForUser(int $userId)` and `getTenantsByAppForUser(int $userId)`; resolves the `User` model internally; encapsulates all `UserApp` and `UserAppTenant` queries
  - `App\Repositories\Central\UserRepository` — three new methods added: `findByEmail(string $email): ?UserRepositoryData`, `verifyPassword(int $id, string $password): bool`, `createSanctumToken(int $id, string $name, array $abilities): string`
  - `App\Auth\Data\Core\*CoreData` → `App\Auth\Data\*Data` — five DTO classes renamed and moved to the flat `Data/` directory: `AppAccessData` (gains `?description` field), `AuthTokenData`, `LoginCredentialsData`, `TenantAccessData`, `UserData`
  - Controllers (`LoginController`, `AppPickerController`, `WebLoginController`, `WebAppPickerController`) — updated to inject services; pass `$user->id` to `AppService::loadApps()` rather than the model instance

### Tests

- **`App\Auth\Tests\AuthServiceTest`** (new, 5 tests) — `login()` returns correct `AuthTokenData`; token abilities include `app:{slug}`; app and tenant access is included; throws `AuthenticationException` for wrong password and unknown email
- **`App\Auth\Tests\AppServiceTest`** (new, 5 tests) — `loadApps()` returns empty collection, correct app data, tenant access, user isolation, and multiple apps
- **20 Auth tests passing** — 10 existing integration tests (`LoginTest`, `AppPickerTest`) + 10 new service tests

---

## [2.4.0] — 2026-06-23

### Changed

- **`TenantHealthService` — model access extracted to repositories** — direct `Tenant`, `Report`, and `DB::table()` calls moved out of the service into two new repository classes under `App\Repositories\Central\`, following the pattern established by `UserRepository` and `ProfileService`:
  - `App\Repositories\Central\TenantRepository` — `allWithMigrationVersions()` (eager-loads `migrationVersions` relation) and `userCountByTenant()` (aggregates `user_app_tenants` pivot via `DB::table`)
  - `App\Repositories\Central\ReportRepository` — `pendingCountByTenant()`, `failedCountByTenant()`, `lastSuccessAtByTenant()` (each returns a `tenant_id → value` pluck map)
  - `TenantHealthService` — constructor now receives `TenantRepository` and `ReportRepository` via injection; `getTenants()`, `getSummary()`, and `computeHealthStatus()` are unchanged in behaviour; no model or `DB` imports remain

### Tests

- **195 PHPUnit tests** — unchanged count; all 14 `TenantHealthServiceTest` tests pass without modification (service resolved via `app()` so the container wires the new repositories automatically)

---

## [2.3.0] — 2026-06-22

### Changed

- **Module structure refactored** — controllers relocated from the central `app/Http/Controllers/` tree into their owning modules, matching the colocation pattern already used by `app/Auth/` and `app/Reports/`:
  - `app/Http/Controllers/Admin/TenantHealthController` → `app/Admin/Http/Controllers/TenantHealthController` (`App\Admin\Http\Controllers`)
  - `app/Http/Controllers/Auth/WebLoginController` → `app/Auth/Http/Controllers/WebLoginController` (`App\Auth\Http\Controllers`)
  - `app/Http/Controllers/Auth/WebAppPickerController` → `app/Auth/Http/Controllers/WebAppPickerController` (`App\Auth\Http\Controllers`)
  - `app/Http/Controllers/Profile/ProfileController` → `app/Profile/Http/Controllers/ProfileController` (`App\Profile\Http\Controllers`)
  - API controllers (`LoginController`, `AppPickerController`) moved from `app/Auth/Http/Controllers/Auth/` (incorrect extra subdirectory) to `app/Auth/Http/Controllers/` — namespace corrected from `App\Auth\Http\Controllers\Auth` to `App\Auth\Http\Controllers`
  - All route files updated to reference the new namespaces

- **Form Request classes extracted** — inline `$request->validate()` calls replaced with dedicated `FormRequest` classes across all modules:

  | Class | Module | Validates |
  |---|---|---|
  | `App\Auth\Http\Requests\WebLoginRequest` | Auth | `email` (required, email), `password` (required) |
  | `App\Auth\Http\Requests\SelectAppRequest` | Auth | `slug` (required, string) |
  | `App\Profile\Http\Requests\UpdateNameRequest` | Profile | `name` (required, string, max:255) |
  | `App\Profile\Http\Requests\UpdatePictureRequest` | Profile | `profile_picture` (required, image, max:2048) |
  | `App\Profile\Http\Requests\UpdatePasswordRequest` | Profile | `current_password` (required, current\_password), `password` (required, confirmed, min:8) |

  The three Profile request classes are shared between `ProfileController` (web/session) and `ProfileApiController` (API/Bearer) — no duplication.

- **Admin module layered** — `TenantHealthService` extracted from `TenantHealthController`, two data classes added:
  - `App\Admin\Services\TenantHealthService` — all query and health-computation logic (`getTenants()`, `getSummary()`, `computeHealthStatus()`); controller is now a thin 3-line delegate
  - `App\Admin\Data\TenantHealthData` — Spatie Data object representing one tenant's health snapshot (13 typed fields)
  - `App\Admin\Data\TenantHealthSummaryData` — Spatie Data object for the four summary counts

- **Profile module layered** — added `Services/` and `Data/` layers matching the Admin pattern:
  - `App\Profile\Services\ProfileService` — `getProfile()`, `updateName()`, `updatePicture()`, `updatePassword()`; used by both web and API controllers
  - `App\Profile\Data\ProfileData` — Spatie Data object (`id`, `name`, `email`, `profilePicture`)
  - `App\Data\Repositories\Central\UserRepositoryData` — underlying repository data shape
  - `App\Repositories\Central\UserRepository` — thin repository wrapping `User` model operations

- **`HandleInertiaRequests` — `auth.user` now lazily resolved** — `auth` was previously evaluated eagerly (non-closure), which could expose a stale in-memory model instance on navigation. Changed to a closure evaluated at serialization time; `auth.user` is now explicitly shaped to only the fields the frontend needs:
  ```php
  'auth' => fn () => ['user' => $this->resolveAuthUser($request)]
  ```
  `resolveAuthUser()` returns `null` for guests or an array of `{ id, name, email, profile_picture_url }` for authenticated users — `profile_picture_url` is always freshly computed from `Storage::disk('public')->url()`, eliminating the stale-URL bug seen after profile picture changes.

- **`Admin/Tenants/Index.vue` sidebar** — hardcoded `"Super Admin"` / `"admin@system.com"` placeholder replaced with live `page.props.auth.user` data, matching `Admin/Index.vue`; profile picture, initial-letter fallback, profile link, and logout button all wired up.

- **`User` model — `profile_picture_url` converted to proper accessor** — removed the `toArray()` override that appended `profile_picture_url` and replaced it with an Eloquent `Attribute::get()` accessor (`profilePictureUrl()`). The accessor is auto-included in serialization and is correctly understood by Larastan.

### Fixed

- **`ProfileService::updatePassword` spurious `return`** — method is declared `void` but had `return $this->userRepository->updatePassword(...)`, causing a fatal error when the profile routes were first hit after the module restructure.
- **`UserRepository` constructor threw `Exception('Not implemented')`** — a scaffolding placeholder left in the constructor prevented the Profile controllers from resolving via the container.

### Added (PHPStan)

- `/** @mixin Report */` on `ReportResource` — resolves all "undefined property" errors for proxied model attributes accessed via `$this->` inside `toArray()`.
- `/** @mixin User */` on `UserResource` — same fix; `profile_picture_url` is now computed inline from the raw `profile_picture` column (which PHPStan can see through the mixin) rather than via the accessor.
- Zero PHPStan errors at level 5.

### Tests

- **195 PHPUnit tests** — up from 110; no tests removed or skipped.
- **`app/Admin/Tests/` test suite** — registered as `Admin` in `phpunit.xml`:
  - `TenantHealthServiceTest` (12 tests) — unit tests for `TenantHealthService` covering each health status transition, user-count aggregation, and `getSummary()` counts
  - `TenantHealthWebTest` (7 tests) — HTTP tests for `GET /admin/tenants`: auth guard, payload structure, `tenants.*` field presence, all three health statuses
- **`app/Profile/Tests/` test suite** — registered as `Profile` in `phpunit.xml`:
  - `ProfileServiceTest` (5 tests) — `getProfile`, `updateName`, `updatePicture`, `updatePassword`, and not-found exception
  - `ProfileWebTest` (16 tests) — guest redirects, view, name/picture/password happy paths and all validation edge cases
  - `ProfileApiTest` (14 tests) — same coverage over the API surface (`actingAs sanctum`, JSON assertions)
- **`WebAuthenticationFlowTest`** — 9 new tests: login validation (missing email, bad format, missing password, wrong credentials), app picker view, app picker `select` happy paths (admin, tenant), access-denied for wrong slug, missing slug validation, unauthenticated guard.
- **`ProfileApiControllerTest`** — new file (13 tests) covering `GET`, `PUT /name`, `POST /picture`, and `PUT /password` over the API surface.

---

## [2.2.0] — 2026-06-22

### Added
- **API versioning** — all API routes are now served under the `/api/v1/` prefix via a `Route::prefix('v1')` wrapper in `routes/api.php`. The glob-loader that auto-discovers `app/*/Routes/api_*.php` files is unchanged; the prefix is applied at the top level so no individual route files needed updating.

- **Eloquent API Resources** — two resource classes provide a consistent `{ "data": { ... } }` envelope for JSON responses:
  - `App\Http\Resources\UserResource` — exposes `id`, `name`, `email`, `profile_picture_url`, `created_at`; hidden fields (`password`, `remember_token`) are never leaked
  - `App\Http\Resources\Reports\ReportResource` — exposes `id`, `type`, `format`, `delivery`, `status`, `parameters`, `batch_id`, `error_message`, `started_at`, `completed_at`, `created_at`; `file_path` is intentionally excluded from the public shape (download is via the dedicated endpoint)
  - `ReportController::index` returns `ReportResource::collection($reports)` — paginated responses include the full `links` + `meta` envelope alongside `data`
  - `ReportController::store` returns `(new ReportResource($report))->response()->setStatusCode(201)`
  - `ReportController::show` returns `new ReportResource($report)`

- **Profile API module** (`app/Profile/`) — user self-service operations are now available over stateless Bearer token auth alongside the existing Inertia web surface:

  | Method | Endpoint | Response |
  |---|---|---|
  | `GET` | `/api/v1/profile` | `UserResource` |
  | `PUT` | `/api/v1/profile/name` | `UserResource` (updated) |
  | `POST` | `/api/v1/profile/picture` | `UserResource` (with `profile_picture_url`) |
  | `PUT` | `/api/v1/profile/password` | `{ "message": "Password updated successfully." }` |

  The API controller (`App\Profile\Http\Controllers\ProfileApiController`) shares all business logic (validation rules, storage handling, hash strategy) with the existing `ProfileController` — neither delegates to the other; both are thin controllers calling the same framework primitives.

### Changed
- **`ReportController`** — `index` return type changed from `JsonResponse` to `AnonymousResourceCollection`; `store` changed to return `JsonResponse` (resource response); `show` return type changed to `ReportResource`
- **All feature tests** — API endpoint paths updated from `/api/*` to `/api/v1/*` across `LoginTest`, `AppPickerTest`, `ReportDispatchTest`, `ReportDownloadTest`, `ReportBatchTest`, and `ReportSubscriptionTest`

### Updated
- **Postman collection** — all API request URLs and path arrays updated to `/api/v1/`; report response bodies updated to reflect the `{ "data": { ... } }` `ReportResource` envelope; `List Reports` response updated to include `links` + `meta` pagination envelope; "Dispatch Single Report" test script updated from `json.id` → `json.data.id`; new **Profile (API)** folder added with 4 requests (`Get Profile`, `Update Display Name`, `Upload Profile Picture`, `Update Password`)

### Tests
- 110 PHPUnit tests pass — no tests removed or skipped

---

## [2.1.0] — 2026-06-21

### Added
- **User profile page** — authenticated users can manage their own account at `GET /profile`:
  - **Display name** — `PUT /profile/name` validates and saves a new name (max 255 chars); the updated name is reflected immediately in the sidebar/nav on next page load via the Inertia `auth.user` shared prop
  - **Profile picture** — `POST /profile/picture` accepts an image file (JPG, PNG, GIF, max 2 MB); stored on the `public` disk under `profile-pictures/` with `0644` permissions so it is web-accessible; the old file is deleted when a new one is uploaded; the public URL is exposed as `profile_picture_url` on the `auth.user` shared prop via a `toArray()` override on the `User` model
  - **Password change** — `PUT /profile/password` requires the current password (`current_password` validation rule) and a confirmed new password (min 8 characters); password is hashed before storage
  - Flash success messages shared globally via `flash.success` in `HandleInertiaRequests::share()` and displayed as a dismissible banner on the profile page
- **Profile picture in sidebar/nav** — Admin and Tenant pages now render the user's avatar in the user section:
  - Shows the uploaded picture when `profile_picture_url` is set, falls back to the initial-letter circle when not
  - Clicking the user section (avatar + name + email) navigates to `/profile` via Inertia `<Link>`
- **`profile_picture` column** — nullable `string` added to the `users` table via migration `2026_06_21_040058_add_profile_picture_to_users_table`
- **Public disk permissions** — `config/filesystems.php` updated with explicit `permissions` (files `0644`, dirs `0755`) and `directory_visibility: public` on the `public` disk to ensure uploaded files are readable by the web server inside Docker

### Routes added

| Method | Endpoint | Middleware | Description |
|---|---|---|---|
| `GET` | `/profile` | `auth` | Show the profile page |
| `PUT` | `/profile/name` | `auth` | Update display name |
| `POST` | `/profile/picture` | `auth` | Upload or replace profile picture |
| `PUT` | `/profile/password` | `auth` | Change password |

### Tests
- **`tests/Feature/Profile/ProfileControllerTest.php`** — 13 PHPUnit feature tests:
  - `test_guest_is_redirected_from_profile` — asserts unauthenticated request redirects to `/login`
  - `test_authenticated_user_can_view_profile_page` — asserts 200 + Inertia component `Profile/Index`
  - `test_user_can_update_display_name` — asserts name saved to DB and flash success set
  - `test_update_name_requires_name` — asserts `name` field error on empty submit
  - `test_update_name_enforces_max_length` — asserts error when name exceeds 255 chars
  - `test_user_can_upload_profile_picture` — asserts file stored on `public` disk and `profile_picture` saved to DB
  - `test_uploading_new_picture_deletes_old_one` — asserts old file removed from `public` disk on re-upload
  - `test_profile_picture_must_be_an_image` — asserts PDF is rejected with a validation error
  - `test_profile_picture_must_not_exceed_2mb` — asserts file over 2 MB is rejected
  - `test_user_can_update_password` — asserts new password hash saved and flash success set
  - `test_password_update_requires_correct_current_password` — asserts `current_password` error on wrong password
  - `test_password_update_requires_confirmation` — asserts `password` error when confirmation does not match
  - `test_new_password_must_be_at_least_8_characters` — asserts error on passwords shorter than 8 chars

### Updated
- **Postman collection** — new "Profile (Web)" folder with 3 requests documenting the web profile endpoints (`Update Display Name`, `Upload Profile Picture`, `Update Password`)

---

## [2.0.0] — 2026-06-21

### Added
- **Login redirect for authenticated users** — `GET /login` now checks whether the visitor is already authenticated before rendering the form:
  - Users with **one app** are redirected directly to that app's dashboard (`/admin` or `/tenant`)
  - Users with **multiple apps** are redirected to `/apps` (the app picker)
  - Users with **no apps** are redirected to `/apps` (which itself redirects back to `/login`)
  - The same redirect logic is reused from the post-login flow via the shared `redirectBasedOnApps()` helper on `WebLoginController`
- **Web logout** — `POST /logout` (session-based, `auth` middleware) added to `WebLoginController`:
  - Calls `Auth::logout()`, invalidates the session, and regenerates the CSRF token
  - Redirects to `GET /login` on success
  - Unauthenticated requests are redirected to login by the `auth` middleware
  - Logout button added to every authenticated page:
    - **Admin** — icon button in the sidebar user section
    - **Tenant** — icon button in the top nav
    - **Reports** — icon button in the top nav
    - **AppPicker** — "Sign out" inline text link
- **Configurable idle session timeout** — inactive browser sessions are automatically logged out after a configurable number of minutes:
  - Timeout duration read from the `authentication_idle_time` system setting (default: `30` minutes)
  - `idleTimeoutMinutes` shared to all Inertia pages via `HandleInertiaRequests::share()` — `null` for unauthenticated visitors
  - `resources/js/composables/useIdleTimeout.js` — Vue composable that tracks activity events (`mousemove`, `mousedown`, `keydown`, `scroll`, `touchstart`, `click`) and fires `POST /logout` after the configured idle period; timers are reset on any activity and cleaned up on component unmount
  - Composable mounted on all authenticated pages: `Admin/Index.vue`, `Tenant/Index.vue`, `Reports/Index.vue`, `Auth/AppPicker.vue`

### Tests
- **`tests/Feature/Auth/WebAuthenticationFlowTest.php`** — 11 PHPUnit feature tests:
  - `test_guest_can_view_login_page` — asserts 200 + Inertia component `Login/Index`
  - `test_authenticated_user_visiting_login_is_redirected_to_apps_when_multiple_apps` — asserts redirect to `/apps` when user has 2+ apps
  - `test_authenticated_user_visiting_login_is_redirected_directly_to_app_when_single_app` — asserts direct redirect to `/admin` when user has exactly 1 app
  - `test_authenticated_user_with_no_apps_visiting_login_is_redirected_to_apps` — asserts redirect to `/apps` when user has no apps
  - `test_login_redirects_to_apps_when_user_has_multiple_apps` — asserts post-login redirect to `/apps`
  - `test_login_redirects_directly_to_app_when_user_has_single_app` — asserts post-login direct redirect to `/admin`
  - `test_logout_clears_session_and_redirects_to_login` — asserts session invalidation and redirect
  - `test_unauthenticated_user_cannot_access_logout` — asserts `auth` middleware redirects to login
  - `test_idle_timeout_minutes_is_shared_with_authenticated_pages` — asserts `idleTimeoutMinutes` prop reflects `authentication_idle_time` setting
  - `test_idle_timeout_is_null_for_guests` — asserts prop is `null` on the login page
  - `test_idle_timeout_defaults_to_30_when_setting_not_configured` — asserts default of 30 when setting is absent

### Updated
- **Postman collection** — Auth folder updated:
  - Renamed existing "Logout" to "Logout (API)" to distinguish from the web flow
  - Added "Logout (Web)" entry documenting `POST /logout` (session-based, CSRF required, returns `302 → /login`)
  - Added folder-level description explaining the API vs. web auth distinction and the idle timeout behaviour

---

## [1.9.0] — 2026-06-20

### Added
- **Tenant health dashboard** — new admin page at `/admin/tenants` that gives a live overview of every tenant's operational state:
  - Per-tenant health status computed as `healthy` / `warning` / `critical`:
    - `critical` — tenant is inactive **or** has failed reports
    - `warning` — no users provisioned, or last migration is more than 30 days old
    - `healthy` — everything else
  - Metrics surfaced per tenant: active status, user count, migration count + last migration timestamp, pending/failed report counts, last successful report, and read-replica configuration
  - Summary bar at the top shows totals for Healthy, Warning, and Critical across all tenants
  - Sidebar "Tenants" link in the Admin layout now navigates to this dashboard
- **Web login flow with app picker** — browser-based login now routes users intelligently after authentication:
  - Users with **one app** are redirected directly to that app (`/admin` or `/tenant`)
  - Users with **multiple apps** are shown an app picker page (`/apps`) to choose which app to open
  - Choosing an app from the picker navigates to that app's root route
- **Improved login error display** — failed login attempts now show a prominent red banner at the top of the form instead of only an inline field error; invalid field borders are highlighted red

### Fixed
- **API login route name collision** — `POST /api/login` was named `login`, shadowing the web `GET /login` route; Sanctum middleware was redirecting unauthenticated API requests to the POST-only endpoint causing `MethodNotAllowedHttpException`; renamed to `api.login`

### Infrastructure
- **Docker: `ext-gd` added** — `phpoffice/phpspreadsheet` requires the GD extension; added `libpng-dev`, `libjpeg-turbo-dev`, `freetype-dev` to `apk` and `gd` to `docker-php-ext-install` in `docker/php/Dockerfile`
- **Docker: composer layer optimised** — `composer.json` and `composer.lock` are now copied before the rest of the app so the `composer install` layer is cached independently of code changes; `--no-scripts` prevents `package:discover` from running before `artisan` exists; scripts run explicitly after `COPY . .`
- **Docker: `COMPOSER_MEMORY_LIMIT=-1`** — removes the default 1.5 GB cap to prevent OOM failures during `--optimize-autoloader` in Alpine containers

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
