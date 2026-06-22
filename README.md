# laravel-multitenant-sso-boilerplate

A production-ready Laravel boilerplate for building multi-tenant SaaS platforms with Single Sign-On, granular per-user permissions, dynamic tenant database resolution, and a scalable async reporting engine.

---

## What's included

| Module | Route prefix | Responsibility |
|---|---|---|
| **Auth** | `/api/v1/login`, `/api/v1/logout`, `/api/v1/apps` | SSO — issues Sanctum token, returns app + tenant access list |
| **Web Auth** | `/login`, `/logout`, `/apps` | Browser session flow — login, logout, app picker |
| **Admin** | `/api/v1/admin/...` | Manage users, permissions, tenant DBs, system settings |
| **Tenant** | `/api/v1/tenant/...` | Transactional app — reads and writes to tenant DB |
| **Reports** | `/api/v1/reports/...` | Read-only heavy queries, async generation, replica support |
| **Profile (API)** | `/api/v1/profile/...` | User self-service — name, picture, password (Bearer token) |
| **Profile (Web)** | `/profile/...` | User self-service — same actions via Inertia/session |

---

## Architecture overview

```
┌─────────────────────────────────────────────────────┐
│                /api/v1/login                        │
│        Single login — issues Sanctum token          │
└────────────┬───────────────────┬────────────────────┘
             │                   │
     ┌───────▼──────┐   ┌────────▼──────────┐
     │/api/v1/admin │   │ /api/v1/tenant     │
     │              │   │ /api/v1/reports    │
     │              │   │ /api/v1/profile    │
     └───────┬──────┘   └────────┬───────────┘
             │                   │
     ┌───────▼───────────────────▼─────────┐
     │              Central DB             │
     │  users, apps, tenants, permissions  │
     └─────────────────────────────────────┘
                         │
          ┌──────────────┼──────────────┐
          │              │              │
   ┌──────▼──────┐ ┌─────▼──────┐ ┌───▼────────┐
   │ tenant_acme │ │tenant_globex│ │ tenant_xyz │
   │    _db      │ │    _db      │ │    _db     │
   │(any server) │ │(any server) │ │(any server)│
   └─────────────┘ └────────────┘ └────────────┘
```

---

## Key features

**Single Sign-On**
- One login endpoint for all modules via Laravel Sanctum
- Token carries user identity and per-app tenant access list
- App picker response — lists all accessible apps and tenants after login

**Two-dimensional permissions**
- Control which apps a user can access
- Control which tenant databases a user can access within each app
- Role support per app and per tenant (`admin`, `user`, `readonly`)

**Dynamic tenant database resolution**
- Each tenant's DB credentials (host, port, name, username, password) stored in the central DB
- Tenant connection resolved at runtime per request via middleware
- Supports any DB server — AWS RDS, DigitalOcean, GCP, on-premise, or any MySQL/PostgreSQL host

**Scalable async reporting**
- Separate `Reports` module — reports are dispatched as queued jobs and tracked through `pending → processing → success / failed` states
- Supports multiple output formats: on-screen (JSON), PDF, and Excel
- Multiple delivery modes: download, email, S3, or email + S3
- Individual and batch report generation — batch jobs use `Bus::batch()` with automatic ZIP packaging on completion
- Error messages surfaced to users on failure
- Laravel Horizon 5 dashboard at `/horizon` for queue monitoring
- Per-tenant read replica support — each tenant can optionally use a dedicated read replica; SELECT queries route to the replica automatically via Laravel's `read`/`write` connection split with `sticky: true`

**Per-tenant scheduled report subscriptions**
- Tenants subscribe to recurring reports with a configurable frequency (`daily`, `weekly`, `monthly`)
- Delivery options: `email` (file attached), `s3` (uploaded to configurable S3 path), or `email_and_s3`
- `php artisan reports:dispatch-subscriptions` checks due subscriptions across all tenants every minute and dispatches `GenerateReportJob` per subscription
- Subscriptions can be paused (`is_active: false`) or deleted individually without affecting other tenants
- Full CRUD REST API under `/api/reports/subscriptions`

**Admin panel**
- Manage users and assign app + tenant DB access
- Add new tenant databases and run migrations from the UI
- System-wide settings management

**Inertia.js + Vue 3 frontend**
- Vue 3 page components served via Inertia.js — no separate frontend server
- Page components live in `resources/js/Pages/` per module, built by Vite
- Active tenant and user permissions shared to every page via Inertia shared props

---

## Project structure

```
laravel-multitenant-sso-boilerplate/
│
├── app/
│   ├── Admin/
│   │   ├── Data/
│   │   │   ├── TenantHealthData.php            # Spatie Data — per-tenant health snapshot (13 fields)
│   │   │   └── TenantHealthSummaryData.php     # Spatie Data — healthy/warning/critical totals
│   │   ├── Http/Controllers/
│   │   │   └── TenantHealthController.php      # GET /admin/tenants — delegates to TenantHealthService
│   │   ├── Routes/
│   │   │   └── web_admin.php                   # Admin web routes
│   │   ├── Services/
│   │   │   └── TenantHealthService.php         # getTenants(), getSummary(), computeHealthStatus()
│   │   └── Tests/
│   │       ├── TenantHealthServiceTest.php     # 12 service-layer unit tests
│   │       └── TenantHealthWebTest.php         # 7 HTTP tests for GET /admin/tenants
│   │
│   ├── Auth/
│   │   ├── Actions/
│   │   │   ├── LoginAction.php
│   │   │   └── LoadUserAppsAction.php
│   │   ├── Data/Core/                          # spatie/laravel-data DTOs
│   │   │   ├── AppAccessCoreData.php
│   │   │   ├── AuthTokenCoreData.php
│   │   │   ├── LoginCredentialsCoreData.php
│   │   │   ├── TenantAccessCoreData.php
│   │   │   └── UserCoreData.php
│   │   ├── Enums/
│   │   │   └── Role.php
│   │   ├── Http/Controllers/
│   │   │   ├── AppPickerController.php         # API — GET /api/v1/apps
│   │   │   ├── LoginController.php             # API — POST /api/v1/login, /logout
│   │   │   ├── WebAppPickerController.php      # Web — GET /apps, POST /apps/select
│   │   │   └── WebLoginController.php          # Web — GET /login, POST /login
│   │   ├── Http/Requests/
│   │   │   ├── SelectAppRequest.php            # slug (required, string)
│   │   │   └── WebLoginRequest.php             # email (required, email), password (required)
│   │   ├── Routes/
│   │   │   ├── api_login.php                   # SSO API routes
│   │   │   └── web_login.php                   # Web login + app picker routes
│   │   └── Tests/
│   │       ├── AppPickerTest.php
│   │       └── LoginTest.php
│   │
│   ├── Http/
│   │   ├── Controllers/Controller.php
│   │   ├── Middleware/HandleInertiaRequests.php # Inertia shared props — auth (closure), flash, tenant, app, role
│   │   ├── Middleware/RequireRole.php
│   │   └── Middleware/ResolveTenantDatabase.php
│   ├── Http/Resources/
│   │   ├── UserResource.php                    # { id, name, email, profile_picture_url, created_at }
│   │   └── Reports/
│   │       └── ReportResource.php              # { id, type, format, delivery, status, parameters, ... }
│   │
│   ├── Models/
│   │   ├── Central/                        # Central DB models (App\Models\Central)
│   │   │   ├── App.php
│   │   │   ├── Report.php
│   │   │   ├── SystemSetting.php
│   │   │   ├── Tenant.php
│   │   │   ├── TenantMigrationVersion.php
│   │   │   ├── User.php
│   │   │   ├── UserApp.php
│   │   │   └── UserAppTenant.php
│   │   └── Tenant/                         # Tenant DB models (App\Models\Tenant)
│   │       └── ReportSubscription.php
│   │
│   ├── Console/Commands/
│   │   ├── CentralMigrateCommand.php           # php artisan central:migrate
│   │   ├── DispatchScheduledReportsCommand.php  # php artisan reports:dispatch-subscriptions
│   │   ├── TenantMigrateCommand.php             # php artisan tenant:migrate
│   │   └── TenantMigrateStatusCommand.php       # php artisan tenant:migrate:status
│   │
│   ├── Http/
│   │   ├── Controllers/Controller.php
│   │   ├── Middleware/HandleInertiaRequests.php
│   │   ├── Middleware/RequireRole.php
│   │   └── Middleware/ResolveTenantDatabase.php
│   │
│   ├── Providers/AppServiceProvider.php
│   │
│   ├── Reports/
│   │   ├── Contracts/
│   │   │   └── ReportGenerator.php         # Generator interface
│   │   ├── Data/
│   │   │   ├── ReportRequestData.php       # Input DTO
│   │   │   └── ReportResultData.php        # Output DTO
│   │   ├── Enums/
│   │   │   ├── ReportDelivery.php          # Download | Email | S3 | EmailAndS3 | None
│   │   │   ├── ReportFormat.php            # Screen | Pdf | Excel
│   │   │   ├── ReportFrequency.php         # Daily | Weekly | Monthly
│   │   │   └── ReportStatus.php            # Pending | Processing | Success | Failed
│   │   ├── Generators/
│   │   │   ├── ReportGeneratorFactory.php  # Resolves generator by format
│   │   │   ├── ScreenReportGenerator.php   # JSON data output (working)
│   │   │   ├── PdfReportGenerator.php      # Stub — needs barryvdh/laravel-dompdf
│   │   │   └── ExcelReportGenerator.php    # Stub — needs maatwebsite/excel
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── ReportController.php             # index, store, batch, show, download, destroy
│   │   │   │   └── ReportSubscriptionController.php # index, store, show, update, destroy
│   │   │   └── Requests/
│   │   │       ├── StoreReportRequest.php
│   │   │       ├── StoreBatchReportRequest.php
│   │   │       ├── StoreReportSubscriptionRequest.php
│   │   │       └── UpdateReportSubscriptionRequest.php
│   │   ├── Jobs/
│   │   │   ├── GenerateReportJob.php       # ShouldQueue, Batchable — routes post-gen delivery
│   │   │   └── GenerateReportBatchJob.php  # Bus::batch() dispatcher
│   │   ├── Mail/
│   │   │   ├── ReportReadyMail.php         # On-demand email (user's own report)
│   │   │   └── ScheduledReportMail.php     # Scheduled delivery — attaches file, ShouldQueue
│   │   ├── Policies/
│   │   │   ├── ReportPolicy.php
│   │   │   └── ReportSubscriptionPolicy.php
│   │   ├── Services/
│   │   │   ├── ReportFileService.php       # store, zip, merge (stub)
│   │   │   └── ReportDeliveryService.php   # sendToRecipients, uploadToS3
│   │   └── Routes/
│   │       └── api_reports.php             # Reports + subscription API routes
│   │
│   ├── Profile/
│   │   ├── Data/
│   │   │   └── ProfileData.php             # Spatie Data — { id, name, email, profilePicture }
│   │   ├── Http/Controllers/
│   │   │   ├── ProfileApiController.php    # GET, PUT /name, POST /picture, PUT /password (API/Bearer)
│   │   │   └── ProfileController.php       # GET /profile, PUT /name, POST /picture, PUT /password (web/session)
│   │   ├── Http/Requests/
│   │   │   ├── UpdateNameRequest.php       # name (required, string, max:255)
│   │   │   ├── UpdatePasswordRequest.php   # current_password, password (confirmed, min:8)
│   │   │   └── UpdatePictureRequest.php    # profile_picture (image, max:2048)
│   │   ├── Routes/
│   │   │   ├── api_profile.php             # Profile API routes under /api/v1/profile
│   │   │   └── web_profile.php             # Profile web routes under /profile
│   │   ├── Services/
│   │   │   └── ProfileService.php          # getProfile(), updateName(), updatePicture(), updatePassword()
│   │   └── Tests/
│   │       ├── ProfileApiTest.php          # 14 API-surface tests (Sanctum Bearer)
│   │       ├── ProfileServiceTest.php      # 5 service-layer unit tests
│   │       └── ProfileWebTest.php          # 16 web-surface tests (session)
│   │
│   └── Tenant/
│       └── Routes/
│           └── api_tenant.php              # Tenant API routes
│
├── database/
│   ├── factories/
│   │   ├── Auth/                           # Auth model factories
│   │   ├── Reports/
│   │   │   └── ReportFactory.php           # States: pending, processing, success, failed, screen, pdf, excel
│   │   └── UserFactory.php
│   ├── migrations/
│   │   ├── central/                        # Central DB migrations
│   │   │   ├── create_users_table.php
│   │   │   ├── create_cache_table.php
│   │   │   ├── create_jobs_table.php
│   │   │   ├── create_personal_access_tokens_table.php
│   │   │   ├── create_apps_table.php
│   │   │   ├── create_tenants_table.php
│   │   │   ├── create_user_apps_table.php
│   │   │   ├── create_user_app_tenants_table.php
│   │   │   ├── create_system_settings_table.php
│   │   │   └── create_reports_table.php
│   │   └── tenant/                         # Per-tenant migrations (companies, properties, floors, units, leases, lease_documents, lease_renewals)
│   └── seeders/
│       ├── DatabaseSeeder.php
│       └── TenantSeeder.php
│
├── resources/
│   ├── js/
│   │   ├── Pages/
│   │   │   ├── Admin/Index.vue             # Admin landing page
│   │   │   ├── Admin/Index.test.js
│   │   │   ├── Admin/Tenants/Index.vue     # Tenant health dashboard
│   │   │   ├── Auth/AppPicker.vue          # Multi-app picker shown after login
│   │   │   ├── Login/Index.vue             # Login page with error banner
│   │   │   ├── Login/Index.test.js
│   │   │   ├── Reports/Index.vue           # Reports landing page
│   │   │   ├── Reports/Index.test.js
│   │   │   ├── Tenant/Index.vue            # Tenant portal landing page
│   │   │   ├── Tenant/Index.test.js
│   │   │   ├── Profile/Index.vue           # User profile page (name, picture, password)
│   │   │   └── Profile/Index.test.js
│   │   ├── composables/
│   │   │   └── useIdleTimeout.js           # Idle session timeout composable
│   │   ├── e2e/
│   │   │   ├── admin.spec.js
│   │   │   ├── login.spec.js
│   │   │   ├── reports.spec.js
│   │   │   ├── tenant.spec.js
│   │   │   └── helpers/auth.js
│   │   └── app.js                          # Inertia bootstrap
│   ├── css/app.css
│   └── views/app.blade.php
│
├── routes/
│   ├── api.php                             # Loads all app/*/Routes/ files under /api/v1/ prefix
│   ├── web.php
│   └── console.php
│
├── docker/
│   ├── nginx/default.conf
│   └── php/Dockerfile
│
├── postman/
│   └── laravel-multitenant-sso.postman_collection.json
│
├── docker-compose.yml
├── phpstan.neon
├── phpunit.xml
├── vite.config.js
├── commitlint.config.js
├── CLAUDE.md
└── README.md
```

---

## Central DB schema

```
users                      — authentication, core identity
apps                       — registered apps (login, admin, tenant, reports)
tenants                    — tenant DB credentials (host, port, name, user, pass) + optional read replica (host, port, user, pass)
user_apps                  — which apps a user can access + role
user_app_tenants           — which tenant DBs a user can access per app + role + default
system_settings            — global config
reports                    — async report jobs (status, format, delivery, file path, error)
tenant_migration_versions  — per-tenant migration history synced from each tenant's migrations table
```

---

## Current state

See [RELEASE_NOTES.md](RELEASE_NOTES.md) for a full changelog.

What's built:
- **Laravel 13** — framework at repo root
- **Laravel Boost 2.4** — starter kit scaffolding
- **Laravel Sanctum 4.0** — API token authentication
- **spatie/laravel-data 4.23** — DTOs under `App\Auth\Data\Core\` using `*CoreData` suffix
- **SSO backend** — login, logout, and app-picker API under `App\Auth\`
- **Central models** — `App`, `Tenant`, `User`, `UserApp`, `UserAppTenant`, `SystemSetting` under `App\Models\Central\`
- **Central DB schema** — `users`, `apps`, `tenants`, `user_apps`, `user_app_tenants`, `system_settings` in `database/migrations/central/`
- **Separated migrations** — `database/migrations/central/` (runs via `php artisan migrate`) and `database/migrations/tenant/` (runs via `php artisan tenant:migrate` against each tenant's own database)
- **Modular routing** — each module owns its routes under `app/*/Routes/api_*.php`; all API routes versioned under `/api/v1/` via `routes/api.php`
- **Collocated tests** — PHPUnit tests live inside each module (e.g. `app/Auth/Tests/`)
- **Dynamic tenant database resolution** — `ResolveTenantDatabase` middleware reads `X-Tenant` header, verifies user access, and wires up a per-request `tenant` DB connection from credentials stored in the central DB
- **Two-dimensional permissions enforcement** — `ResolveTenantDatabase` enforces both the app dimension (`X-App` header, Sanctum token ability `app:{slug}`, `user_apps` record) and the tenant dimension (`user_app_tenants` scoped to the resolved app); `RequireRole` middleware available for per-route role enforcement (`admin`, `user`, `readonly`)
- **Laravel Horizon 5** — async report queue with dedicated `report-worker` supervisor; Horizon dashboard at `/horizon` gated via `HorizonServiceProvider`; `horizon` Docker service added
- **Async report engine** — `GenerateReportJob` + `GenerateReportBatchJob` dispatched to the `reports` Redis queue; status tracked through `pending → processing → success / failed`; cancelled batches mark all pending reports `Failed`; `Storage::put` failures surface as `Failed` with an error message; screen format fully working; PDF and Excel are stubs awaiting package installation
- **Batch homogeneity enforced** — all reports in a batch must share the same `format` and `delivery`; validated at the API boundary; ZIP archive path persisted on the first report for retrieval via the download endpoint
- **Tenant migration version tracking** — after each `tenant:migrate` run, the applied migrations are synced from the tenant's `migrations` table to the central `tenant_migration_versions` table; `tenant:migrate:status` command shows applied/total count, up-to-date status, and latest migration for each tenant
- **Per-tenant scheduled report subscriptions** — tenants subscribe to recurring reports (`daily` / `weekly` / `monthly`) with `email`, `s3`, or `email_and_s3` delivery; `php artisan reports:dispatch-subscriptions` runs every minute via the scheduler, iterates active tenants, finds due subscriptions, creates central `Report` records, and dispatches `GenerateReportJob`; `ScheduledReportMail` attaches the generated file; `ReportDeliveryService` uploads to S3; full CRUD API at `/api/reports/subscriptions`
- **Tenant health dashboard** — admin page at `/admin/tenants` aggregates per-tenant health metrics (migration compliance, user count, report failures, read-replica status) and computes a `healthy` / `warning` / `critical` status per tenant; summary bar shows totals across all tenants
- **Web login flow with app picker** — after successful login, users with one app are redirected directly; users with multiple apps see an app picker page (`/apps`); login errors display as a prominent red banner
- **Authentication flow (Phase 5.1)** — authenticated users visiting `/login` are redirected instead of seeing the form; web logout (`POST /logout`) invalidates the session and is available on every page; configurable idle session timeout auto-logs out inactive browser sessions based on the `authentication_idle_time` system setting (default 30 min)
- **User self-service profile (Phase 5.2)** — authenticated users can update their display name, upload/replace their profile picture, and change their password at `/profile`; profile picture stored on the `public` disk and exposed as `profile_picture_url` on the `auth.user` Inertia shared prop; Admin and Tenant pages display the avatar in the sidebar/nav with a link to the profile page
- **RESTful API + Inertia.js combined architecture** — Inertia.js web routes (session auth) coexist with a versioned RESTful API (`/api/v1/`) under Sanctum token auth; Eloquent API Resources (`UserResource`, `ReportResource`) provide consistent `{ "data": {...} }` envelopes; `Profile` module exposes all profile operations over both surfaces independently
- **Inertia.js + Vue 3** — installed and wired up with `HandleInertiaRequests` middleware
- **Frontend landing pages** — dark-themed Vue 3 SFCs for Login, Admin, Tenant, and Reports at `/login`, `/admin`, `/tenant`, `/reports`
- **Vitest unit tests** — component tests for all four page components
- **Playwright E2E tests** — browser tests for all four pages against a live Laravel server
- **GitHub Actions CI** — build, unit test, and E2E test jobs on every push and PR
- **PHPStan + Larastan** — static analysis at level 5 targeting PHP 8.5; zero errors
- **Tailwind CSS** via `@tailwindcss/vite`
- **Bunny Fonts** (`Instrument Sans`) via `laravel-vite-plugin`
- **commitlint 21 + Husky 9** — conventional commit enforcement
- **Postman collection** — all SSO endpoints documented and ready to import
- **Docker Compose** — full local dev stack (Nginx + PHP-FPM 8.5 + PostgreSQL 17 + Redis 7); PHP image tuned for PHP 8.5 Alpine (bundled `pdo`/`opcache`, added `zlib-dev`/`icu-libs`)

---

## API — Postman collection

A Postman collection is included at [`postman/laravel-multitenant-sso.postman_collection.json`](postman/laravel-multitenant-sso.postman_collection.json).

Import via **Postman → Import → File**. The collection uses two variables — `base_url` (default `http://localhost`) and `token` — and covers all endpoints. All API endpoints are under `/api/v1/` and use Bearer token auth (Sanctum).

**Auth**

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `POST` | `/api/v1/login` | — | Authenticate and receive a Bearer token + app list |
| `POST` | `/api/v1/logout` | Bearer | Revoke the current token |
| `GET` | `/api/v1/apps` | Bearer | List accessible apps and tenant clients |

**Reports**

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `GET` | `/api/v1/reports` | Bearer | List the authenticated user's reports (paginated) |
| `POST` | `/api/v1/reports` | Bearer | Dispatch a single report job |
| `POST` | `/api/v1/reports/batch` | Bearer | Dispatch up to 50 report jobs as a batch (same format + delivery required) |
| `GET` | `/api/v1/reports/{id}` | Bearer | Poll status and result for a report |
| `GET` | `/api/v1/reports/{id}/download` | Bearer | Stream the generated file (success only) |
| `DELETE` | `/api/v1/reports/{id}` | Bearer | Delete a report and its stored file |

**Report Subscriptions**

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `GET` | `/api/v1/reports/subscriptions` | Bearer | List subscriptions for the current tenant (paginated) |
| `POST` | `/api/v1/reports/subscriptions` | Bearer | Create a new scheduled report subscription |
| `GET` | `/api/v1/reports/subscriptions/{id}` | Bearer | Get a subscription by ID |
| `PUT` | `/api/v1/reports/subscriptions/{id}` | Bearer | Update a subscription (all fields optional) |
| `DELETE` | `/api/v1/reports/subscriptions/{id}` | Bearer | Delete a subscription |

**Profile (API — Bearer token auth)**

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `GET` | `/api/v1/profile` | Bearer | Get the authenticated user's profile |
| `PUT` | `/api/v1/profile/name` | Bearer | Update display name |
| `POST` | `/api/v1/profile/picture` | Bearer | Upload or replace profile picture (image, max 2 MB) |
| `PUT` | `/api/v1/profile/password` | Bearer | Change password (requires current password) |

**Profile (Web — session auth)**

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `GET` | `/profile` | Session | Show the user profile page (Inertia) |
| `PUT` | `/profile/name` | Session | Update display name |
| `POST` | `/profile/picture` | Session | Upload or replace profile picture (image, max 2 MB) |
| `PUT` | `/profile/password` | Session | Change password (requires current password) |

The Login request includes a test script that automatically saves the returned token to `{{token}}`. The "Dispatch Single Report" request saves the returned UUID to `{{report_id}}`, and "Create Subscription" saves the ID to `{{subscription_id}}`, so subsequent requests work without manual copy-paste.

---

## Getting started

### Requirements

- PHP 8.5+
- Composer
- Node.js 20+
- Docker Engine 29+ and Docker Compose (recommended for local dev)
- PostgreSQL 17+ (central DB server)
- Redis 7+ (cache, sessions, and queue backend)

### Installation

```bash
git clone https://github.com/ronmartdanieljavier/laravel-multitenant-sso-boilerplate.git
cd laravel-multitenant-sso-boilerplate

# Install PHP dependencies
composer install

# Install Node dependencies (commitlint + Husky)
npm install

# Copy environment file and generate app key
cp .env.example .env
php artisan key:generate

# Build frontend assets
npm run build

# Or run Vite dev server during development
npm run dev
```

### Default admin account

A superuser and two default apps are automatically created during `php artisan migrate`. Credentials are read from `.env`:

| Variable | Default |
|---|---|
| `ADMIN_NAME` | `Admin` |
| `ADMIN_EMAIL` | `admin@example.com` |
| `ADMIN_PASSWORD` | `password` |

Two apps are seeded on a fresh install:

| App | Slug | Access |
|---|---|---|
| **Admin** | `admin` | Central administration — users, apps, tenants, settings |
| **Tenant** | `tenant` | Tenant portal — connects to a tenant database |

The default admin account is granted the `admin` role on both apps and linked to the Demo Tenant database under the `tenant` app.

**Permission model** — access is two-dimensional:
- A user can have **Admin only** — manages tenants through the central DB, no tenant DB connection
- A user can have **Tenant only** — accesses one or more tenant databases, no admin panel
- A user can have **both** — full access to admin panel and tenant databases

### Run migrations

```bash
# Central DB only (users, apps, tenants, permissions, cache, jobs)
# Also creates the default admin account from .env
php artisan migrate

# All tenant DBs — reads credentials from the tenants table, creates each DB if needed
php artisan tenant:migrate

# Single tenant
php artisan tenant:migrate --tenant=demo
```

Additional flags available on `tenant:migrate`: `--fresh`, `--seed`, `--rollback`, `--step`, `--force`.

```bash
# Check which tenants are up to date and how many migrations have been applied
php artisan tenant:migrate:status

# Check a single tenant
php artisan tenant:migrate:status --tenant=demo
```

### Environment variables

Key variables in `.env`:

```bash
APP_URL=http://localhost

DB_CONNECTION=sqlite          # or pgsql for production
CENTRAL_DB_HOST=127.0.0.1
CENTRAL_DB_DATABASE=central_db
CENTRAL_DB_USERNAME=central_user
CENTRAL_DB_PASSWORD=secret

# Default admin account (seeded automatically on migrate)
ADMIN_NAME="Admin"
ADMIN_EMAIL=admin@example.com    # login email for the default superuser
ADMIN_PASSWORD=password          # change before deploying to production

# Reports / queue
REDIS_HOST=127.0.0.1
QUEUE_CONNECTION=redis
```

---

## Docker local development

A `docker-compose.yml` at the repo root spins up the full stack — Nginx, PHP-FPM, PostgreSQL, and Redis — with a single command.

```bash
# Copy the pre-configured Docker environment file and generate an app key
cp .env.docker .env
php artisan key:generate

# Build and start all containers
docker compose up -d --build

# Run central migrations (creates the tenants table, seeds a Demo Tenant, and creates the default admin account)
docker compose exec php php artisan migrate

# Run tenant migrations (creates tenant_demo database and migrates it)
docker compose exec php php artisan tenant:migrate
```

Services exposed:

| Service | URL / Port |
|---|---|
| App (via Nginx) | http://localhost:80 |
| PostgreSQL | localhost:5432 |
| Redis | localhost:6379 |

The stack:
- **nginx** — serves static assets and proxies PHP requests to `php:9000`
- **php** — PHP 8.5-FPM with `pdo_pgsql`, `redis`, `mbstring`, `zip`, `bcmath`, `intl`, `opcache`
- **horizon** — dedicated container running `php artisan horizon`; processes the `default` and `reports` queues; restarts automatically
- **postgres** — PostgreSQL 17, data persisted in `postgres_data` volume
- **redis** — Redis 7, data persisted in `redis_data` volume; used for cache, sessions, and queues

`.env.docker` pre-configures `DB_CONNECTION=pgsql`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`, and `CACHE_STORE=redis`.

```bash
# Rebuild containers after Dockerfile changes
docker compose build --no-cache

# Run artisan commands inside the php container
docker compose exec php php artisan migrate

# Tail logs for all services
docker compose logs -f
```

---

## Inertia.js + Vue 3

Controllers return `Inertia::render()` instead of JSON or Blade — data is injected directly into Vue pages as props.

```php
// app/Http/Controllers/DashboardController.php
public function index(): Response
{
    return Inertia::render('Tenant/Dashboard', [
        'stats' => DashboardService::stats(),
    ]);
}
```

```vue
<!-- resources/js/Pages/Tenant/Dashboard.vue -->
<script setup>
defineProps({ stats: Object })
</script>
```

### Shared props

The active tenant and user permissions are shared globally via `HandleInertiaRequests` so every Vue page gets them without an extra API call:

```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'auth'                => fn () => ['user' => $this->resolveAuthUser($request)],
        'flash'               => fn () => ['success' => $request->session()->get('success')],
        'tenant'              => fn () => $request->attributes->get('current_tenant'),
        'app'                 => fn () => $request->attributes->get('current_app'),
        'role'                => fn () => $request->attributes->get('current_role'),
        'idleTimeoutMinutes'  => fn () => $request->user()
            ? (int) SystemSetting::get('authentication_idle_time', 30)
            : null,
    ];
}

private function resolveAuthUser(Request $request): ?array
{
    $user = $request->user();
    if (! $user) {
        return null;
    }
    return [
        'id'                  => $user->id,
        'name'                => $user->name,
        'email'               => $user->email,
        'profile_picture_url' => $user->profile_picture
            ? Storage::disk('public')->url($user->profile_picture)
            : null,
    ];
}
```

`auth` is a closure so it is evaluated lazily — after all middleware has run — preventing stale model data from being serialised during navigation. The user shape is explicit: only the four fields the frontend needs are included, and `profile_picture_url` is always recomputed from the raw column value.

---

## Static analysis — PHPStan

PHPStan + Larastan is configured in `phpstan.neon` at level 5 targeting PHP 8.5:

```bash
./vendor/bin/phpstan analyse --memory-limit=512M
```

> **Note:** PHP's default CLI memory limit (128M) is too low for PHPStan to complete analysis. Pass `--memory-limit=512M` or raise `memory_limit` in your `php.ini`.

---

## Commit linting

commitlint 21 with Husky 9 enforces conventional commits on every commit.

```bash
# Test a commit message manually
echo "feat(auth): add passkey login support" | npx commitlint
```

Valid examples:

```bash
git commit -m "feat(auth): add SSO token refresh flow"
git commit -m "fix(tenant): resolve DB connection leak on client switch"
git commit -m "docs: update provisioning guide in README"
git commit -m "chore(docker): add redis healthcheck to compose file"
```

---

## Tech stack

| Layer | Package | Status |
|---|---|---|
| Framework | Laravel 13 | ✅ Installed |
| Starter kit | Laravel Boost 2.4 | ✅ Installed |
| API auth | Laravel Sanctum 4.0 | ✅ Installed |
| DTOs | spatie/laravel-data 4.23 | ✅ Installed |
| SSO backend | Login, logout, app-picker API | ✅ Built |
| Frontend | Vue 3 + Inertia.js | ✅ Installed |
| Frontend pages | Login, Admin, Tenant, Reports landing pages | ✅ Built |
| CSS | Tailwind CSS (`@tailwindcss/vite`) | ✅ Installed |
| Fonts | Bunny Fonts — Instrument Sans | ✅ Installed |
| Build | Vite + `laravel-vite-plugin` | ✅ Installed |
| Unit tests | Vitest 4 + Vue Test Utils 2 | ✅ Installed |
| E2E tests | Playwright 1.61 (Chromium) | ✅ Installed |
| DB | PostgreSQL 17 (central + tenant) | ✅ Configured |
| Containers | Docker Engine 29 + Docker Compose | ✅ Working |
| Commit linting | commitlint 21 + Husky 9 | ✅ Installed |
| CI | GitHub Actions (build, unit, E2E) | ✅ Active |
| API client | Postman collection | ✅ Included |
| AI coding | Claude Code (Anthropic) | ✅ Active |
| Queue | Laravel Horizon 5 + Redis 7 | ✅ Installed |
| Static analysis | PHPStan + Larastan (level 5) | ✅ Active |
| Tenant middleware | Dynamic DB resolution + two-dimensional enforcement | ✅ Built |
| Two-dimensional permissions | App + tenant DB | ✅ Built |

---

## Roadmap

**Phase 1 — Foundation** *(done)*
- [x] Laravel 13 installed at repo root
- [x] Laravel Boost + Sanctum + Tailwind CSS + Vite
- [x] commitlint + Husky conventional commits
- [x] Claude Code `CLAUDE.md` integration

**Phase 2 — SSO backend** *(done)*
- [x] Central DB schema — users, apps, tenants, user_apps, user_app_tenants, system_settings
- [x] `App\Auth\` module — models, DTOs, actions, controllers
- [x] SSO API — `POST /api/login`, `POST /api/logout`, `GET /api/apps`
- [x] Sanctum token with per-app abilities embedded as token scopes
- [x] Modular routing — each module owns `app/*/Routes/api_*.php`
- [x] Collocated PHPUnit tests — `app/Auth/Tests/` registered as `Auth` suite
- [x] Postman collection — `postman/laravel-multitenant-sso.postman_collection.json`
- [x] Separated migrations — `database/migrations/central/` and `database/migrations/tenant/`
- [x] `php artisan tenant:migrate` custom command — auto-creates tenant database, runs tenant migrations per tenant
- [x] Docker Compose full local dev stack (Nginx + PHP-FPM 8.5 + PostgreSQL 17 + Redis 7)
- [x] Demo Tenant seeded automatically in central migration with encrypted credentials
- [x] Tenant migrations run against isolated `tenant_demo` database (not central)

**Phase 3 — Frontend & multi-tenancy** *(in progress)*
- [x] Inertia.js + Vue 3 installed and configured
- [x] Landing pages for Login, Admin, Tenant, and Reports modules
- [x] Vitest unit tests for all four page components
- [x] Playwright E2E tests for all four pages
- [x] GitHub Actions CI — frontend build, unit, and E2E jobs
- [x] Dynamic tenant database resolution middleware (`ResolveTenantDatabase` — `X-Tenant` header, user access check, per-request `tenant` DB connection)
- [x] PHPStan + Larastan static analysis at level 5 (zero errors)
- [x] Two-dimensional permissions enforcement — `X-App` + `X-Tenant` headers, token ability check, `user_apps`/`user_app_tenants` enforcement, `RequireRole` middleware
- [x] Inertia shared props — active tenant + permissions on every page

**Phase 4 — Reporting & ops** *(done)*
- [x] Laravel Horizon + Redis async report queue
- [x] Per-tenant read replica support
- [x] Tenant migration version tracking
- [x] Per-tenant scheduled report subscriptions (email/S3 delivery)
- [x] Tenant health dashboard in admin

**Phase 5 — Authentication UX & admin management** *(in progress)*

*5.1 — Authentication flow* *(done)*
- [x] Authenticated users visiting `/login` are redirected — to `/apps` if they have multiple app accesses, or directly to their app dashboard if they have only one
- [x] Logout available on all pages
- [x] Configurable idle session timeout (driven by the `authentication_idle_time` system setting)

*5.2 — User self-service* *(done)*
- [x] User can update their display name
- [x] User can upload and update their profile picture
- [x] User can reset their own password

*5.2.1 — RESTful API + Inertia.js combined* *(done)*
- [x] All API routes versioned under `/api/v1/` prefix
- [x] `UserResource` and `ReportResource` Eloquent API Resources
- [x] `Profile` API module — full profile CRUD over Bearer token (alongside existing Inertia web surface)
- [x] Postman collection updated with Profile (API) folder and v1 paths

*5.3 — System settings (admin)*
- [ ] Admin can manage system-wide settings:
  - Default email service — SMTP **or** Postmark (only one active at a time)
  - Authentication idle timeout duration
  - Default S3 storage settings
- [ ] A persistent notification banner is shown on all admin pages for any required system setting that is unset, with a direct link to the settings page

*5.4 — App management (admin)*
- [ ] Admin can edit app information (name, description)

*5.5 — User management (admin)*
- [ ] Admin can invite a user by email — before sending the invitation the admin selects which apps the user can access and, if the app has tenants, which tenant(s) the user belongs to; the account is inactive until the invitation is accepted
- [ ] Admin can edit a user's profile data and app/tenant permissions

*5.6 — Tenant management (admin)*
- [ ] Admin can add and edit tenant details and database connection settings
- [ ] When a new tenant is created, its tenant database migrations run automatically
- [ ] Admin can trigger migrations on a selected tenant or across all tenants from the UI
- [ ] Admin can toggle a tenant's `is_active` flag — when deactivated, all users with a tenant role on that tenant are force-logged out (tokens revoked)
- [ ] Admin can delete a tenant — the tenant's database is dropped and all related central records are removed

*5.7 — Tenant settings (admin)*
- [ ] Admin can add, update, and delete per-tenant settings:
  - **Email service** — SMTP or Postmark (only one active at a time); if unset, the system default email service is used
  - **S3 settings** — bucket, region, and credentials; if unset, the system default S3 settings are used
  - **Report PDF header and footer** — configurable text and optional logo upload, with a live frontend preview
  - **Report server settings** — any tenant-specific overrides for report generation (e.g. queue, timeout)
- [ ] The tenant's mail driver and S3 disk are resolved at runtime per request so all outbound emails and file uploads from a tenant context use that tenant's configured service

---

## Claude Code

This project is built with [Claude Code](https://claude.ai/code) — Anthropic's agentic coding tool — as part of the development workflow.

```bash
# Install Claude Code globally
npm install -g @anthropic-ai/claude-code

# Run from the repo root
claude
```

---

## License

MIT — Ron Mart Daniel Javier
