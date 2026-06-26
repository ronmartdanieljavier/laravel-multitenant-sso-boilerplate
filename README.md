# laravel-multitenant-sso-boilerplate

A production-ready Laravel boilerplate for building multi-tenant SaaS platforms with Single Sign-On, granular per-user permissions, dynamic tenant database resolution, and a scalable async reporting engine.

---

## What's included

| Module | Route prefix | Auth | Responsibility |
|---|---|---|---|
| **Auth (API)** | `/api/v1/login` `/api/v1/logout` `/api/v1/apps` | — / Bearer | SSO — issues Sanctum token scoped per app, returns accessible app + tenant list |
| **Auth (Web)** | `/login` `/logout` `/apps` | Session | Browser login form, app picker, idle session timeout, redirect-if-authenticated guard |
| **Admin — Dashboard** | `/admin` `/api/v1/admin/dashboard` | Admin | Platform snapshot: stat cards, tenant health bar, pending invitations, error log summary, report queue health, migration compliance |
| **Admin — Users** | `/admin/users` `/api/v1/admin/users` `/invitation/{token}` | Admin / — | Invite users with per-app + per-tenant permissions; edit profile and permissions; invitation acceptance flow |
| **Admin — Apps** | `/admin/apps` `/api/v1/admin/apps` | Admin | View and edit registered app names and descriptions |
| **Admin — Tenants** | `/admin/tenants` `/api/v1/admin/tenants` | Admin | Create, edit, delete tenants; run migrations; toggle active/maintenance; online-user count per tenant; force logout per user |
| **Admin — Tenant Settings** | `/admin/tenants/{t}/settings` `/api/v1/admin/tenants/{t}/settings` | Admin | Per-tenant overrides: email driver, S3 storage, report PDF header/footer + logo, report queue/timeout/Redis connection, branding |
| **Admin — Tenant Users** | `/admin/tenants/{t}/users` `/api/v1/admin/tenants/{t}/users` | Admin | Per-tenant user list with live online badge; force logout individual users |
| **Admin — Tenant Reports** | `/admin/tenants/{t}/reports` `/api/v1/admin/tenants/{t}/reports` | Admin | Admin view of any tenant's report job queue (status, format, user, duration) |
| **Admin — Tenant Errors** | `/admin/tenants/{t}/errors` `/api/v1/admin/tenants/{t}/errors` | Admin | Per-tenant exception log: list, filter, detail with stack trace, resolve/reopen, delete; global error-code lookup |
| **Admin — System Settings** | `/admin/settings` `/api/v1/admin/settings` | Admin | Seven-tab system config: Email, SMS, Push, Storage, Authentication, Security, Branding; amber banner when required settings are unset |
| **Tenant (Web)** | `/tenant` `/tenant/reports` `/documents` `/tenant/errors` | Session | Persistent sidebar portal — dashboard, report queue, document manager, and error log (read-only) |
| **Tenant Error Logs (Portal API)** | `/api/v1/tenant/errors` | Bearer + X-App + X-Tenant | Read-only portal API for tenant users to view their own error log entries; omits stack trace and request headers |
| **Documents (API)** | `/api/v1/documents` | Bearer + X-App + X-Tenant | Upload, list (paginated), show, download, and delete tenant documents; files stored on the tenant's configured storage disk |
| **Reports (API)** | `/api/v1/reports` | Bearer + X-App + X-Tenant | Dispatch single and batch report jobs; poll status; download files; manage scheduled subscriptions (daily/weekly/monthly, email/S3 delivery) |
| **Profile (API)** | `/api/v1/profile` | Bearer | Update display name, upload profile picture, change password |
| **Profile (Web)** | `/profile` | Session | Same self-service actions via Inertia; sidebar includes App Selection link to return to the app picker |

---

## Architecture overview

```
┌─────────────────────────────────────────────────────────────────────────┐
│                          Browser / API Client                            │
└───────────────┬─────────────────────────────────────┬────────────────────┘
                │  Web (session cookie)                │  API (Sanctum Bearer)
                │  /login  /admin/*  /tenant/*         │  /api/v1/login
                │  /profile  /invitation/*             │  /api/v1/admin/*
                │                                      │  /api/v1/tenant/*
                ▼                                      ▼  /api/v1/reports/*
┌────────────────────────────────────────────────────────────────────────┐
│                       Laravel 13 + Inertia.js v3                        │
│                                                                         │
│  ┌───────────┐  ┌───────────┐  ┌──────────┐  ┌──────────┐  ┌────────┐ │
│  │   Auth    │  │   Admin   │  │  Tenant  │  │ Reports  │  │Profile │ │
│  │  Module   │  │  Module   │  │  Module  │  │  Module  │  │ Module │ │
│  └─────┬─────┘  └─────┬─────┘  └────┬─────┘  └────┬─────┘  └───┬────┘ │
│        └──────────────┴─────────────┴──────────────┴────────────┘      │
│                                      │                                   │
│               Controller → Service → Repository → Model                  │
│               (no Eloquent outside Repositories; DTOs at every boundary) │
└──────────────────────────────────────┬──────────────────────────────────┘
                                       │
                   ┌───────────────────┼──────────────────────┐
                   │                   │                       │
        ┌──────────▼──────────┐  ┌─────▼──────┐  ┌───────────▼───────────┐
        │     Central DB       │  │   Redis     │  │   Horizon Workers     │
        │     PostgreSQL       │  │  cache      │  │   (reports queue)     │
        │  users  apps         │  │  sessions   │  │  GenerateReportJob    │
        │  tenants  settings   │  │  queues     │  │  GenerateReportBatch  │
        │  reports  error_logs │  └─────────────┘  │  ScheduledReports     │
        └──────────┬───────────┘                   └───────────────────────┘
                   │
                   │  ResolveTenantDatabase (API) — X-Tenant header
                   │  ResolveWebTenantDatabase (Web) — session key
                   │  → per-request DB connection from Central DB
                   │
        ┌──────────┼──────────────┐
        │          │              │
  ┌─────▼──────┐ ┌─▼──────────┐ ┌▼──────────┐
  │ tenant_    │ │ tenant_    │ │ tenant_   │
  │ acme_db   │ │ globex_db  │ │ xyz_db    │
  │ (any host) │ │ (any host) │ │ (any host)│
  │ + replica  │ │ + replica  │ │           │
  └────────────┘ └────────────┘ └───────────┘
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
- **Live admin dashboard** — single-page snapshot of the entire platform: user/app/tenant/SSO-session stat cards, tenant health bar (healthy/warning/critical) with click-to-filter, pending invitation list with one-click resend, recent users table with edit modal shortcut, cross-tenant unresolved error log summary by severity, report queue health (pending/processing/failed per tenant), and tenant migration compliance (behind tenants listed with one-click Run migrations); also exposed as `GET /api/v1/admin/dashboard` for mobile and external consumers
- **Quick actions** — `+ Invite User`, `+ Add Tenant`, and `Settings` shortcut buttons in the dashboard header; each deep-links directly into the target page's modal
- Manage users and assign app + tenant DB access
- Add new tenant databases and run migrations from the UI
- System-wide settings across seven tabs: Email (SMTP/Postmark/Mailgun/SES), SMS (Twilio/Vonage/SNS), Push (FCM/APNs/OneSignal), Storage (Local/S3/R2/GCS/FTP/SFTP), Authentication, Security, and Branding
- Email footer signature editor (TipTap rich text) with unsubscribe URL for CAN-SPAM/GDPR compliance
- Persistent amber banner on all admin pages when required settings are unset
- **Per-tenant settings** — each tenant can override email driver, S3/R2 storage, report PDF header/footer (rich-text editor with live A4 preview), report queue/timeout/Redis connection, and branding; unset fields fall back to system settings at runtime
- **Tenant users** — dedicated per-tenant user list with inline app-permission editing; live "Online" badge per user (pulsing green when a Sanctum token is active); per-user Force Logout button revokes all tokens immediately; tenant list shows logged-in count per tenant alongside total users
- **Tenant report queue** — admin can view all report jobs for any tenant (status, format, user, duration) from the admin panel at `/admin/tenants/{tenant}/reports`
- **Tenant error logs** — every unhandled exception in a tenant request context is recorded with full stack trace, sanitized request params/headers, user ID, and IP; in production the raw error is replaced by a support-friendly error code (`E-ACME-A3F9B12C`); admin can list, filter, view detail, resolve, and delete logs per tenant; support teams can look up any error code globally via API
- **App Selection link on every admin page** — a consistent "App Selection" link (grid icon, href `/apps`) appears in the sidebar footer of every admin page (Dashboard, Users, Apps, Tenants, Settings, and all admin tenant sub-pages) so admins can return to the app picker without signing out
- **Persistent tenant sub-navigation** — admin users navigating between tenant-specific pages (Settings, Users, Reports, Errors) stay in context via a sticky sub-nav bar; no need to return to the tenants list to switch pages

**Tenant portal**
- Persistent sidebar layout (`TenantLayout.vue`) so users navigate between Dashboard, Report Queue, Documents, and Error Logs without page flicker; all portal pages use full-width layout matching the admin panel
- Report queue page at `/tenant/reports` — live status polling every 4 s, stat cards, format/status badges, download links
- **Tenant switcher** — users assigned to more than one tenant see a dropdown in the sidebar below the tenant name; selecting a tenant posts to `POST /tenant/switch`, updates `session('tenant_app_current_tenant')`, and reloads the page with the new tenant's DB connection wired in

**Inertia.js + Vue 3 frontend**
- Vue 3 page components served via Inertia.js — no separate frontend server
- Page components live in `resources/js/Pages/` per module, built by Vite
- Active tenant and user permissions shared to every page via Inertia shared props
- Persistent layouts (`TenantLayout`, `AdminTenantLayout`) keep sidebars and sub-nav in place during navigation
- **Guided page tours** — every page auto-starts a contextual tour on first visit (driver.js); tours are skippable, and a floating `?` button lets users retrigger any tour; tour-seen state is persisted per page in `localStorage`; dark-theme CSS overrides built into `TourButton.vue`; `useTour` composable (`resources/js/composables/useTour.js`) provides a consistent API across all 12 pages

---

## Project structure

```
laravel-multitenant-sso-boilerplate/
│
├── app/
│   ├── Admin/
│   │   ├── Data/
│   │   │   ├── CreateTenantData.php            # Spatie Data — new tenant payload (name, slug, db_*, read_replica_*)
│   │   │   ├── InviteUserData.php              # Spatie Data — invite payload (name, email, apps DataCollection)
│   │   │   ├── MissingSystemSettingsData.php   # Spatie Data — list of unset required setting labels
│   │   │   ├── SystemSettingsData.php          # Spatie Data — all 85 system settings (camelCase → snake_case JSON)
│   │   │   ├── TenantData.php                  # Spatie Data — tenant with credentials + health fields (15 fields)
│   │   │   ├── TenantHealthData.php            # Spatie Data — per-tenant health snapshot (13 fields)
│   │   │   ├── TenantHealthSummaryData.php     # Spatie Data — healthy/warning/critical totals
│   │   │   ├── UpdateAppData.php               # Spatie Data — app update payload (name, description)
│   │   │   ├── UpdateTenantData.php            # Spatie Data — tenant update payload (same as create; null password = keep existing)
│   │   │   ├── UpdateUserData.php              # Spatie Data — user update payload (name, email, apps)
│   │   │   ├── UserAppData.php                 # Spatie Data — app permission entry (appId, role, tenantIds)
│   │   │   ├── UserAppPermissionData.php       # Spatie Data — invite/update app permission input
│   │   │   └── UserData.php                    # Spatie Data — user with permissions (id, name, email, isActive, apps)
│   │   ├── Http/Controllers/
│   │   │   ├── AppManagementApiController.php      # GET|PUT /api/v1/admin/apps — REST API surface
│   │   │   ├── AppManagementController.php         # GET|PUT /admin/apps — Inertia web surface
│   │   │   ├── InvitationController.php            # GET|POST /invitation/{token} — accept invitation
│   │   │   ├── SystemSettingsApiController.php     # GET|PUT /api/v1/admin/settings — REST API surface
│   │   │   ├── SystemSettingsController.php        # GET|PUT /admin/settings — Inertia web surface
│   │   │   ├── TenantErrorsApiController.php       # GET|PATCH|DELETE /api/v1/admin/tenants/{t}/errors/* + GET /api/v1/admin/errors/{code}
│   │   │   ├── TenantErrorsController.php          # GET|PATCH|DELETE /admin/tenants/{t}/errors/* — Inertia web surface
│   │   │   ├── TenantHealthController.php          # (health service only — superseded by TenantManagementController for web)
│   │   │   ├── TenantManagementApiController.php   # GET|POST|PUT|PATCH|POST|DELETE /api/v1/admin/tenants — REST API surface
│   │   │   ├── TenantManagementController.php      # GET|POST|PUT|PATCH|POST|DELETE /admin/tenants — Inertia web surface
│   │   │   ├── TenantSettingsApiController.php     # GET|PUT|POST|DELETE /api/v1/admin/tenants/{t}/settings/* — REST API surface
│   │   │   ├── TenantSettingsController.php        # GET|PUT|POST|DELETE /admin/tenants/{t}/settings/* — Inertia web surface
│   │   │   ├── TenantUsersApiController.php        # GET|DELETE /api/v1/admin/tenants/{t}/users — REST API surface
│   │   │   ├── TenantUsersController.php           # GET|DELETE /admin/tenants/{t}/users — Inertia web surface
│   │   │   ├── UserManagementApiController.php     # GET|POST|PUT /api/v1/admin/users — REST API surface
│   │   │   └── UserManagementController.php        # GET|POST|PUT /admin/users — Inertia web surface
│   │   ├── Http/Requests/
│   │   │   ├── CreateTenantRequest.php             # name, slug (unique, regex), db_* fields, read_replica_* fields
│   │   │   ├── InviteUserRequest.php               # name, email (unique), apps array validation
│   │   │   ├── UpdateTenantRequest.php             # same as create with slug uniqueness scoped to self
│   │   │   ├── UpdateTenantSettingsRequest.php     # 30+ nullable rules for all tenant-overridable setting keys
│   │   │   ├── UploadTenantLogoRequest.php         # logo: image, mimes png|jpg|jpeg|svg|webp, max 2 MB
│   │   │   └── UpdateUserRequest.php               # name, email, apps array validation
│   │   ├── Mail/
│   │   │   └── UserInvitationMail.php          # Invitation email — accepts UserRepositoryData DTO
│   │   ├── Routes/
│   │   │   ├── web_admin.php                   # Admin web routes
│   │   │   └── api_admin.php                   # Admin API routes
│   │   ├── Services/
│   │   │   ├── SystemSettingsService.php       # getSettings()→SystemSettingsData, updateSettings(), getMissingRequiredSettings()→MissingSystemSettingsData
│   │   │   ├── TenantErrorContext.php          # Static per-request bridge between reportable() and renderable() exception callbacks
│   │   │   ├── TenantErrorLogService.php       # record(), listForTenant(), getByCode(), getById(), resolve(), unresolve(), delete()
│   │   │   ├── TenantHealthService.php         # getTenants(), getSummary(), computeHealthStatus()
│   │   │   ├── TenantManagementService.php     # list()→Collection<TenantData>, create(), update(), setActive(), delete(), runMigrations()
│   │   │   ├── TenantSettingsService.php       # getSettings()→TenantSettingsData, updateSettings(), uploadLogo(), deleteLogo(), resolveMailConfig(), resolveS3Config(), resolveUploadConstraints()
│   │   │   └── UserManagementService.php       # list()→Collection<UserData>, invite(), update(), acceptInvitation()
│   │   └── Tests/
│   │       ├── AppManagementApiTest.php        # 10 PHPUnit tests — API surface
│   │       ├── AppManagementWebTest.php        # 12 PHPUnit tests — web surface
│   │   │   ├── SystemSettingsApiTest.php       # 33 PHPUnit tests — API surface, all tabs and validation
│   │       ├── SystemSettingsServiceTest.php   # 8 PHPUnit tests — DTO return contracts
│   │       ├── SystemSettingsWebTest.php       # 43 PHPUnit tests — web surface, all tabs and validation
│   │       ├── TenantErrorLogServiceTest.php   # 16 PHPUnit tests — record, sanitize, resolve, isolate, delete
│   │       ├── TenantErrorLogWebTest.php       # 8 PHPUnit tests — list, detail, auth guard, wrong-tenant 404, CRUD
│   │       ├── TenantHealthServiceTest.php     # 14 service-layer unit tests
│   │       ├── TenantHealthWebTest.php         # 7 HTTP tests for GET /admin/tenants
│   │       ├── TenantManagementApiTest.php     # 9 PHPUnit tests — API surface (list, create, update, toggle, migrate, delete)
│   │       ├── TenantManagementServiceTest.php # 13 PHPUnit tests — DTO return contracts, token revocation, default connection, admin auto-assign
│   │       ├── TenantManagementWebTest.php     # 11 PHPUnit tests — web surface (list, CRUD, toggle, delete)
│   │       ├── TenantReportQueueApiTest.php    # 4 PHPUnit tests — admin API report queue
│   │       ├── TenantReportQueueWebTest.php    # 6 PHPUnit tests — admin web report queue
│   │       ├── TenantSettingsApiTest.php       # 9 PHPUnit tests — settings CRUD, logo, report_connection, redis_connections
│   │       ├── TenantSettingsServiceTest.php   # 11 PHPUnit tests — effective fallback, setMany, resolveMailConfig
│   │       ├── TenantSettingsWebTest.php       # 10 PHPUnit tests — web surface, validation, logo, report server settings
│   │       ├── UserManagementApiTest.php       # PHPUnit tests — API surface (list, invite, update, validation)
│   │       ├── UserManagementServiceTest.php   # PHPUnit tests — DTO return contracts
│   │       └── UserManagementWebTest.php       # PHPUnit tests — web surface (list, invite, update)
│   │
│   ├── Auth/
│   │   ├── Data/                               # spatie/laravel-data DTOs
│   │   │   ├── AppAccessData.php
│   │   │   ├── AuthTokenData.php
│   │   │   ├── LoginCredentialsData.php
│   │   │   ├── TenantAccessData.php
│   │   │   └── UserData.php
│   │   ├── Enums/
│   │   │   └── Role.php
│   │   ├── Http/Controllers/
│   │   │   ├── AppPickerController.php         # API — GET /api/v1/apps
│   │   │   ├── LoginController.php             # API — POST /api/v1/login, /logout
│   │   │   ├── WebAppPickerController.php      # Web — GET /apps, POST /apps/select
│   │   │   └── WebLoginController.php          # Web — GET /login, POST /login
│   │   ├── Http/Requests/
│   │   │   ├── LoginRequest.php                # email (required, email), password (required, string)
│   │   │   ├── SelectAppRequest.php            # slug (required, string)
│   │   │   └── WebLoginRequest.php             # email (required, email), password (required)
│   │   ├── Routes/
│   │   │   ├── api_login.php                   # SSO API routes
│   │   │   └── web_login.php                   # Web login + app picker routes
│   │   ├── Services/
│   │   │   ├── AppService.php                  # loadApps() — builds AppAccessData via UserAppRepository
│   │   │   └── AuthService.php                 # login() — authenticates via UserRepository, delegates to AppService
│   │   └── Tests/
│   │       ├── AppPickerTest.php               # API — GET /api/v1/apps
│   │       ├── AppServiceTest.php              # 5 AppService unit tests
│   │       ├── AuthServiceTest.php             # 5 AuthService unit tests
│   │       └── LoginTest.php                   # API — POST /api/v1/login, /logout
│   │
│   ├── Http/
│   │   ├── Controllers/Controller.php
│   │   ├── Middleware/HandleInertiaRequests.php     # Inertia shared props — auth, flash, tenant, app, role, availableTenants, missingRequiredSettings
│   │   ├── Middleware/RequireRole.php
│   │   ├── Middleware/ResolveTenantDatabase.php     # API — resolves tenant from X-App + X-Tenant headers
│   │   ├── Middleware/ResolveWebTenantDatabase.php  # Web — resolves tenant from session key
│   │   └── Requests/Admin/
│   │       └── UpdateSystemSettingsRequest.php  # 80+ validation rules across all 7 setting tabs
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
│   │   │   ├── TenantErrorLog.php          # tenant_id, error_code, exception_class, message, file, line, trace, request_*, severity, context, resolved_at
│   │   │   ├── TenantMigrationVersion.php
│   │   │   ├── TenantSetting.php           # tenant_id, key, value — per-tenant setting overrides
│   │   │   ├── User.php
│   │   │   ├── UserApp.php
│   │   │   └── UserAppTenant.php
│   │   └── Tenant/                         # Tenant DB models (App\Models\Tenant)
│   │       ├── Document.php                # connection=tenant; id, title, description, file_path, file_name, file_size, mime_type, uploaded_by_user_id, uploaded_by_name
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
│   ├── Data/
│   │   └── Repositories/
│   │       └── Central/                    # Repository-layer DTOs (DB → DTO mapping)
│   │           ├── AppRepositoryData.php           # id, name, slug, description, isActive
│   │           ├── ReportRepositoryData.php         # id (UUID string), userId, type, format, status, …
│   │           ├── TenantMigrationVersionRepositoryData.php  # migration, batch, migratedAt
│   │           ├── TenantRepositoryData.php         # id, name, slug, isActive, dbHost/Port/Name/…, hasReadReplica, migrationVersions[]
│   │           ├── UserAppRepositoryData.php        # appId, appName, role, tenantIds[]
│   │           ├── UserRepositoryData.php           # id, name, email, profilePicture, isActive, invitationToken, invitationSentAt
│   │           └── UserWithPermissionsRepositoryData.php  # id, name, email, isActive, invitationSentAt, profilePictureUrl, apps[]
│   │
│   ├── Repositories/
│   │   └── Central/
│   │       ├── AppRepository.php           # listOrdered()→Collection<AppRepositoryData>, update()→AppRepositoryData
│   │       ├── ReportRepository.php        # create()→ReportRepositoryData, listForUser()→LengthAwarePaginator<ReportRepositoryData>
│   │       ├── SystemSettingRepository.php # get(), set()
│   │       ├── TenantRepository.php        # allWithMigrationVersions(), listActive(), listOrdered(), find(), create(), update(), setActive(), delete(), dropDatabase(), getUserIdsForTenant() — all return TenantRepositoryData or void
│   │       ├── UserAppRepository.php       # syncPermissions(), getTenantsForUserAndApp(), getDefaultTenantSlugForUserAndApp()
│   │       └── UserRepository.php          # find(), listWithPermissions(), findWithPermissions(), createInvited(), updateProfile(), activateInvitation(), … — all return DTOs
│   │
│   ├── Tenant/
│   │   ├── Data/
│   │   │   └── TenantData.php              # Spatie Data — { id, name, slug, isCurrent }
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── TenantSwitcherController.php     # POST /tenant/switch (web)
│   │   │   │   └── TenantSwitcherApiController.php  # GET /api/v1/tenant/tenants, POST /api/v1/tenant/switch
│   │   │   └── Requests/
│   │   │       └── SwitchTenantRequest.php          # tenant_slug (required, string)
│   │   ├── Routes/
│   │   │   ├── web_tenant.php              # Tenant web routes (all via ResolveWebTenantDatabase)
│   │   │   └── api_tenant.php              # Tenant API routes
│   │   ├── Services/
│   │   │   └── TenantSwitcherService.php   # getTenantsForUser(), initializeForUser(), switchTenant()
│   │   └── Tests/
│   │       ├── TenantSwitcherApiTest.php   # 4 PHPUnit tests — API surface
│   │       ├── TenantSwitcherServiceTest.php # 7 PHPUnit tests — service layer
│   │       └── TenantSwitcherWebTest.php   # 3 PHPUnit tests — web surface
│   │
│   └── TenantErrors/
│       ├── Data/
│       │   └── TenantErrorData.php         # Spatie Data — portal-safe error DTO (no trace, file, line, or request headers)
│       ├── Http/Controllers/
│       │   ├── TenantErrorsController.php      # GET /tenant/errors, GET /tenant/errors/{id} — Inertia web surface
│       │   └── TenantErrorsApiController.php   # GET /api/v1/tenant/errors, GET /api/v1/tenant/errors/{id} — REST API surface
│       ├── Routes/
│       │   ├── web_tenant_errors.php       # Web routes — auth + ResolveWebTenantDatabase
│       │   └── api_tenant_errors.php       # API routes — auth:sanctum + ResolveTenantDatabase; named tenant.api.errors.*
│       ├── Services/
│       │   └── TenantErrorsPortalService.php   # listForTenant(), getForTenant() — enforces tenant scoping; returns TenantErrorData
│       └── Tests/
│           ├── TenantErrorsApiTest.php     # 7 PHPUnit tests — API surface, scoping, 404, sensitivity mask
│           ├── TenantErrorsServiceTest.php # 7 PHPUnit tests — service layer, scoping, DTO type, sensitivity mask
│           └── TenantErrorsWebTest.php     # 7 PHPUnit tests — web surface, scoping, filter, 404
│
├── Documents/
│   ├── Data/
│   │   └── DocumentData.php            # Spatie Data — { id, title, description, fileName, fileSize, mimeType, uploadedByName, createdAt }
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── DocumentController.php       # GET|POST /documents, GET /documents/{id}/download, DELETE /documents/{id}
│   │   │   └── DocumentApiController.php    # GET|POST /api/v1/documents, GET /api/v1/documents/{id}, GET|DELETE /api/v1/documents/{id}/*
│   │   └── Requests/
│   │       └── StoreDocumentRequest.php     # title (required, max:255), description (nullable, max:2000), file (required, max:20 MB)
│   ├── Routes/
│   │   ├── web_documents.php            # Web routes under auth + ResolveWebTenantDatabase
│   │   └── api_documents.php            # API routes under auth:sanctum + ResolveTenantDatabase
│   ├── Services/
│   │   └── DocumentService.php          # paginate(), list(), find(), store(), downloadResponse(), delete(), getFilePath()
│   └── Tests/
│       ├── DocumentApiTest.php          # 6 PHPUnit tests — API surface
│       ├── DocumentServiceTest.php      # 6 PHPUnit tests — service layer
│       └── DocumentWebTest.php          # 8 PHPUnit tests — web surface
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
│   │   └── tenant/                         # Per-tenant migrations (report_subscriptions only; cleaned up in Phase 6.1)
│   └── seeders/
│       ├── DatabaseSeeder.php
│       └── TenantSeeder.php
│
├── resources/
│   ├── js/
│   │   ├── Layouts/
│   │   │   ├── AdminTenantLayout.vue       # Persistent layout for admin tenant pages — main sidebar + tenant sub-nav (Settings/Users/Reports/Errors)
│   │   │   ├── AdminTenantLayout.test.js   # 13 Vitest tests
│   │   │   ├── TenantLayout.vue            # Persistent layout for tenant portal — tenant switcher, nav, user profile + logout icon
│   │   │   └── TenantLayout.test.js        # 16 Vitest tests
│   │   ├── Pages/
│   │   │   ├── Admin/Index.vue             # Admin landing page (missing-settings banner)
│   │   │   ├── Admin/Index.test.js
│   │   │   ├── Admin/Apps/Index.vue        # App management — inline edit rows
│   │   │   ├── Admin/Apps/Index.test.js
│   │   │   ├── Admin/Users/Index.vue       # User management — invite modal + edit modal with per-app/tenant permission selectors
│   │   │   ├── Admin/Users/Index.test.js
│   │   │   ├── Admin/Users/Accept.vue      # Invitation acceptance page — name + password form
│   │   │   ├── Admin/Tenants/Index.vue     # Tenant health + management dashboard (missing-settings banner, Settings/Users/Reports/Errors buttons per row)
│   │   │   ├── Admin/Tenants/Settings.vue  # Per-tenant settings — 5 tabs (Email/Storage/Report PDF/Report Server/Branding); uses AdminTenantLayout
│   │   │   ├── Admin/Tenants/Settings.test.js
│   │   │   ├── Admin/Tenants/Users.vue     # Per-tenant user list with inline edit modal; uses AdminTenantLayout
│   │   │   ├── Admin/Tenants/Users.test.js
│   │   │   ├── Admin/Tenants/ReportQueue.vue  # Per-tenant report job queue (admin view, live polling); uses AdminTenantLayout
│   │   │   ├── Admin/Tenants/ReportQueue.test.js
│   │   │   ├── Admin/Tenants/Errors.vue    # Per-tenant error log list; uses AdminTenantLayout
│   │   │   ├── Admin/Tenants/Errors.test.js
│   │   │   ├── Admin/Tenants/ErrorDetail.vue  # Error detail with stack trace and resolve/reopen; uses AdminTenantLayout
│   │   │   ├── Admin/Tenants/ErrorDetail.test.js
│   │   │   ├── Admin/Settings/Index.vue    # System settings — 7 tabs, per-tab useForm
│   │   │   ├── Admin/Settings/RichTextEditor.vue  # TipTap editor for email footer signature
│   │   │   ├── Admin/Settings/RichTextEditor.test.js
│   │   │   ├── Auth/AppPicker.vue          # Multi-app picker shown after login
│   │   │   ├── Login/Index.vue             # Login page with error banner
│   │   │   ├── Login/Index.test.js
│   │   │   ├── Reports/Index.vue           # Reports landing page
│   │   │   ├── Reports/Index.test.js
│   │   │   ├── Tenant/Index.vue            # Tenant portal landing page; uses TenantLayout
│   │   │   ├── Tenant/Index.test.js
│   │   │   ├── Tenant/ReportQueue.vue      # Tenant report job queue (live polling, download links); uses TenantLayout
│   │   │   ├── Tenant/ReportQueue.test.js
│   │   │   ├── Tenant/ErrorLogs.vue        # Portal error log list — severity/unresolved filters, stat cards, read-only table; uses TenantLayout
│   │   │   ├── Tenant/ErrorLogs.test.js    # 18 Vitest tests
│   │   │   ├── Tenant/ErrorLog.vue         # Portal error detail — error code, exception summary, request info, context, support note; uses TenantLayout
│   │   │   ├── Tenant/ErrorLog.test.js     # 24 Vitest tests
│   │   │   ├── Profile/Index.vue           # User profile page (name, picture, password)
│   │   │   └── Profile/Index.test.js
│   │   ├── Pages/Partials/
│   │   │   ├── TenantSwitcher.vue          # Tenant switcher dropdown — lists accessible tenants, posts to /tenant/switch
│   │   │   ├── TenantSwitcher.test.js      # 11 Vitest tests
│   │   │   └── TourButton.vue              # Floating ? button (Teleport to body) — triggers page tours; dark-theme driver.js CSS overrides
│   │   ├── composables/
│   │   │   ├── useIdleTimeout.js           # Idle session timeout composable
│   │   │   └── useTour.js                  # driver.js wrapper — auto-start, localStorage persistence, retrigger
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
├── tests/
│   └── Feature/
│       └── Repositories/
│           └── Central/                    # Repository contract tests — verify DTO return types
│               ├── AppRepositoryTest.php
│               ├── ReportRepositoryTest.php
│               ├── TenantRepositoryTest.php
│               └── UserRepositoryTest.php
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
- **spatie/laravel-data 4.23** — DTOs across all modules; repository DTOs in `App\Data\Repositories\Central\`, service/module DTOs in `App\{Module}\Data\`; global `SnakeCaseMapper` maps camelCase properties to snake_case JSON automatically
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
- **Tenant health dashboard** — admin page at `/admin/tenants` aggregates per-tenant health metrics (migration compliance, user count, report failures, read-replica status) and computes a `healthy` / `warning` / `critical` status per tenant; summary bar shows totals across all tenants; data access extracted into `TenantRepository` and `ReportRepository` under `App\Repositories\Central\`
- **Web login flow with app picker** — after successful login, users with one app are redirected directly; users with multiple apps see an app picker page (`/apps`); login errors display as a prominent red banner
- **Authentication flow (Phase 5.1)** — authenticated users visiting `/login` are redirected instead of seeing the form; web logout (`POST /logout`) invalidates the session and is available on every page; configurable idle session timeout auto-logs out inactive browser sessions based on the `authentication_idle_time` system setting (default 30 min)
- **User self-service profile (Phase 5.2)** — authenticated users can update their display name, upload/replace their profile picture, and change their password at `/profile`; profile picture stored on the `public` disk and exposed as `profile_picture_url` on the `auth.user` Inertia shared prop; Admin and Tenant pages display the avatar in the sidebar/nav with a link to the profile page
- **RESTful API + Inertia.js combined architecture** — Inertia.js web routes (session auth) coexist with a versioned RESTful API (`/api/v1/`) under Sanctum token auth; Eloquent API Resources (`UserResource`, `ReportResource`) provide consistent `{ "data": {...} }` envelopes; `Profile` and `SystemSettings` modules expose all operations over both surfaces independently
- **System settings (Phase 5.3)** — seven-tab admin settings page at `/admin/settings` with per-tab save; settings managed via `SystemSettingsService` returning `SystemSettingsData` DTO; required settings (email driver, auth idle timeout, storage driver) trigger a persistent amber banner on all admin pages when unset; full REST API at `/api/v1/admin/settings`; email footer signature uses a TipTap rich-text editor (`RichTextEditor.vue`) outputting HTML stored as a setting value; 76 PHPUnit + 23 Vitest tests
- **App management (Phase 5.4)** — admin can view and edit app information (name, description) at `/admin/apps` and via `GET|PUT /api/v1/admin/apps`; Inertia inline-edit rows; `AppRepositoryData` DTO returned by the repository
- **User management (Phase 5.5)** — admin can invite users by email with per-app and per-tenant permissions via `POST /api/v1/admin/users/invite`; admin can update user profile and permissions via `PUT /api/v1/admin/users/{user}`; invited users accept via `/invitation/{token}`; account stays inactive until accepted; `UserData` and `UserWithPermissionsRepositoryData` DTOs carry the full permission graph
- **Tenant management (Phase 5.6)** — admin can create, edit, and delete tenants from `/admin/tenants`; creating a tenant automatically runs its DB migrations; admin can run migrations per-tenant or across all tenants; toggling `is_active` to false immediately revokes all Sanctum tokens for users on that tenant; deleting a tenant drops its database (best-effort), revokes user tokens, and cascades central record removal; full REST API under `/api/v1/admin/tenants`; `TenantData` DTO combines management fields (DB credentials, `isPasswordSet`) with health metrics for the combined management+health page
- **Tenant provisioning enhancements** — when adding a new tenant with the Database Connection fields left blank, the system automatically fills `db_host`, `db_port`, `db_username`, and `db_password` from the `tenant` connection config (`DB_TENANT_*` env vars), and generates `db_name` as `tenant_{slug}` so each tenant gets an isolated database rather than sharing the central one; `db_host`, `db_name`, `db_username`, and `db_password` are now nullable in the DB (migration `2026_06_25_231939_make_tenant_db_fields_nullable`); after creation, every user with `role = admin` on any app is automatically assigned to the new tenant in `user_app_tenants`
- **Per-tenant settings (Phase 5.7 / 6.3)** — admin configures per-tenant overrides across five tabs (Email, Storage, Report PDF, Report Server, Branding); unset keys fall back to system settings at runtime via `TenantSettingsService::resolveMailConfig()` / `resolveS3Config()`; report jobs routed to tenant-specific queue, timeout, and Redis connection via `ReportController::resolveReportConfig()`; `report_connection` dropdown populated from `config/queue.php` redis entries; full REST API under `/api/v1/admin/tenants/{tenant}/settings`; **Phase 6.3** adds document upload constraints (allowed file type groups and per-type max MB) overridable per-tenant via `TenantSettingsService::resolveUploadConstraints()`
- **Tenant report queue (Phase 5.7 / 6.4)** — tenant users view their queued report jobs at `/tenant/reports` with live 4 s polling via `usePoll`; admin views any tenant's jobs at `/admin/tenants/{tenant}/reports`; both surfaces share `ReportRepository::listForTenant()`; **Phase 6.4** adds a "Generate Report" quick-action button dispatching a one-off job from a modal (type + format selectable), retry failed reports, subscription management UI (create / pause / delete), and auto-registration of completed PDF/Excel reports as Documents with `source: report`; dedicated `reports` filesystem disk (`storage_path('app')`) fixes download 404s from the Laravel 11 `local` disk root change; tenant PDF header/footer from settings rendered as unescaped HTML; `ReportGeneratorFactory` now resolves generators via the container so injected dependencies are wired automatically; API parity: `POST /api/v1/tenant/reports/quick`, `POST /api/v1/tenant/reports/{id}/retry`
- **Live admin dashboard (Phase 5.8)** — single-page snapshot of the entire platform: user/app/tenant/SSO-session stat cards, tenant health bar (healthy/warning/critical) with click-to-filter, pending invitation list with one-click resend, recent users table with edit modal shortcut, cross-tenant unresolved error log summary by severity, report queue health (pending/processing/failed per tenant), and tenant migration compliance (behind tenants listed with one-click Run migrations); also exposed as `GET /api/v1/admin/dashboard` for mobile and external consumers
- **Tenant maintenance mode (Phase 5.9)** — admin can put any individual tenant or all tenants simultaneously into maintenance mode; enabling immediately revokes all Sanctum tokens for affected tenant users (force logout), hides the tenant from the app picker (`AppService.loadApps()`), and returns HTTP 503 on all API requests resolved through that tenant; admin UI on `/admin/tenants` includes per-row toggle buttons and "Maintenance: All On / All Off" bulk buttons; a dedicated "In Maintenance" summary card appears on both the tenant list and admin dashboard; full REST API via `PATCH /api/v1/admin/tenants/{id}/maintenance` and `PATCH /api/v1/admin/tenants/maintenance/all`
- **App tour system** — every page has a guided tour (driver.js) that auto-starts on first visit, can be skipped, and retriggered via a floating `?` button; tour-seen state persisted per page in `localStorage`; 12 pages covered (admin dashboard, tenants, apps, users, settings, tenant settings/users/reports/errors, tenant portal dashboard and report queue, user profile); `useTour` composable + `TourButton.vue` component; all 217 Vitest tests pass with a global driver.js mock in `test-setup.js`
- **Tenant logged-in users (Phase 5.10)** — admin can see how many users are currently online per tenant (green "X online" dot on the tenant list) and view per-user live session status on the tenant users page; Force Logout button immediately revokes all Sanctum tokens for a specific user; `TenantData` gains `loggedInCount`, `UserData` gains `isLoggedIn`; powered by `TenantRepository::loggedInUserCountByTenant()` and `loggedInUserIdsForTenant()` (join on `personal_access_tokens`); full REST API via `DELETE /api/v1/admin/tenants/{tenant}/users/{user}/session`
- **Tenant error log portal view (Phase 6.3)** — tenant users can view their own error log at `/tenant/errors` and inspect individual entries at `/tenant/errors/{id}`; read-only (no resolve/delete); response DTO omits stack trace, file path, line number, and request headers — showing only the error code, exception type, message, severity, status, request URL/method, sanitized params, and context (IP, user agent); a support callout on the detail page prompts users to quote the error code when contacting support; REST API at `/api/v1/tenant/errors` (named `tenant.api.errors.*`); `Error Logs` nav link added to `TenantLayout.vue`; 42 Vitest component tests + 21 PHPUnit tests across web, API, and service layers
- **Persistent navigation layouts** — `TenantLayout.vue` for the tenant portal (tenant switcher + Dashboard + Report Queue + Documents + Error Logs sidebar + App Selection link + user profile/logout footer); `AdminTenantLayout.vue` for admin tenant pages (Settings / Users / Reports / Errors sub-nav + App Selection link); both implemented as Inertia persistent layouts via `defineOptions({ layout })`
- **Documents module (Phase 6.2 / 6.3 / 6.4)** — `app/Documents/` module following the standard layered architecture; upload via a modal triggered from the page header (form resets on close or success); file-type-aware icons in the document table (PDF red, Word blue, Excel emerald, CSV amber, image purple, text slate); upload files via multipart form (streamed with `putFileAs()` for memory efficiency), list with pagination (20 per page), show metadata, download (signed URL for S3/R2; streamed response for local disk), and delete (removes file from storage and DB record); files stored on the tenant's configured storage disk resolved via `TenantSettingsService::resolveDisk()`; web routes at `/documents`; API routes at `/api/v1/documents`; **Phase 6.3** enforces upload constraints (allowed MIME groups and per-type size caps) resolved from system settings and tenant overrides via `StoreDocumentRequest` + `TenantSettingsService::resolveUploadConstraints()`; **Phase 6.4** adds `source` field (`upload` / `report` / `subscription`) to every document record — `DocumentSource` enum, migration, model cast, DTO propagation, and a source badge in `Documents/Index.vue`; `DocumentService::createFromReport()` is called by `GenerateReportJob` after a successful PDF/Excel generation to register the file as a document; download routing checks `source` and uses `Storage::disk('reports')` for report-origin files; 36 PHPUnit tests (9 new) + 20 Vitest tests (new file)
- **Multi-driver tenant DB middleware (Phase 6.2)** — both `ResolveWebTenantDatabase` and `ResolveTenantDatabase` now detect `DB_TENANT_DRIVER` at runtime and build either a PostgreSQL or MySQL connection config; PostgreSQL connections use `schema`, `sslmode`, and `utf8` charset; MySQL connections retain `utf8mb4`/`collation`/`strict`; default port falls back to `5432` (pgsql) or `3306` (mysql); 11 new PHPUnit tests across both middleware classes
- **Tenant switcher (Phase 6.7)** — `TenantSwitcherService` + `TenantSwitcherController` (web) + `TenantSwitcherApiController` (API); `ResolveWebTenantDatabase` middleware for session-based tenant resolution; `TenantSwitcher.vue` dropdown component; `availableTenants` Inertia shared prop; 16 PHPUnit + 27 Vitest tests
- **Repository pattern** — all Eloquent access isolated to `App\Repositories\Central\`; every public repository method returns a DTO, never a model; service layer maps repository DTOs to module DTOs before returning to controllers
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

**System Settings (API — Bearer token auth)**

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `GET` | `/api/v1/admin/settings` | Bearer | Retrieve all settings and the list of unset required settings |
| `PUT` | `/api/v1/admin/settings` | Bearer | Update one or more settings, returns updated data and missing required list |

**System Settings (Web — session auth)**

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `GET` | `/admin/settings` | Session | Show the settings page (Inertia — 7 tabs) |
| `PUT` | `/admin/settings` | Session | Save settings, redirect with flash success |

**User Management (API — Bearer token auth)**

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `GET` | `/api/v1/admin/users` | Bearer | List all users with their app and tenant permissions |
| `POST` | `/api/v1/admin/users/invite` | Bearer | Invite a new user by email and assign app/tenant permissions; account inactive until accepted |
| `PUT` | `/api/v1/admin/users/{user}` | Bearer | Update a user's name, email, and app/tenant permissions |

**Invitation acceptance (Web — unauthenticated)**

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `GET` | `/invitation/{token}` | — | Show the invitation acceptance form |
| `POST` | `/invitation/{token}` | — | Accept the invitation — sets name, password, and activates the account |

**Tenant Management (API — Bearer token auth)**

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `GET` | `/api/v1/admin/tenants` | Bearer | List all tenants with health metrics and DB connection info |
| `POST` | `/api/v1/admin/tenants` | Bearer | Create a new tenant and automatically run its DB migrations |
| `PUT` | `/api/v1/admin/tenants/{tenant}` | Bearer | Update tenant details and DB connection settings |
| `PATCH` | `/api/v1/admin/tenants/{tenant}/active` | Bearer | Activate or deactivate a tenant; deactivating revokes all user tokens |
| `POST` | `/api/v1/admin/tenants/{tenant}/migrate` | Bearer | Run pending migrations for a single tenant |
| `POST` | `/api/v1/admin/tenants/migrate-all` | Bearer | Run pending migrations across all tenant databases |
| `DELETE` | `/api/v1/admin/tenants/{tenant}` | Bearer | Delete a tenant — revokes tokens, drops DB, removes all central records |

**Tenant Switcher (API — Bearer token auth)**

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `GET` | `/api/v1/tenant/tenants` | Bearer | List all tenants accessible to the authenticated user (excludes maintenance); each entry includes `is_current` |
| `POST` | `/api/v1/tenant/switch` | Bearer | Switch the active tenant by slug; updates session; returns 403 if not assigned |

**Tenant Report Queue (API — Bearer + X-App + X-Tenant headers)**

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `GET` | `/api/v1/tenant/reports` | Bearer | List all report jobs for the current tenant (paginated, latest-first) |

**Tenant Error Logs — Portal (API — Bearer + X-App + X-Tenant headers)**

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `GET` | `/api/v1/tenant/errors` | Bearer | List error logs for the current tenant — read-only; omits trace and request headers. Optional: `?severity=error\|warning\|critical`, `?unresolved=1` |
| `GET` | `/api/v1/tenant/errors/{id}` | Bearer | Get a single error log entry for the current tenant; returns 404 if the ID belongs to a different tenant |

**Documents (API — Bearer + X-App + X-Tenant headers)**

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `GET` | `/api/v1/documents` | Bearer | List documents for the current tenant (paginated, 20/page, max 100 via `?per_page=`) |
| `POST` | `/api/v1/documents` | Bearer | Upload a document (multipart — `title`, `description?`, `file`; max 20 MB); saves to tenant storage disk |
| `GET` | `/api/v1/documents/{id}` | Bearer | Get document metadata by ID |
| `GET` | `/api/v1/documents/{id}/download` | Bearer | Download the file — redirect to signed URL for S3/R2, streamed response for local disk |
| `DELETE` | `/api/v1/documents/{id}` | Bearer | Delete a document record and its file from storage |

**Admin Tenant Report Queue (API — Bearer token auth)**

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `GET` | `/api/v1/admin/tenants/{tenant}/reports` | Bearer | List all report jobs for a specific tenant (admin view) |

The Login request includes a test script that automatically saves the returned token to `{{token}}`. The "Dispatch Single Report" request saves the returned UUID to `{{report_id}}`, "Create Subscription" saves the ID to `{{subscription_id}}`, "Invite User" saves the new user ID to `{{invited_user_id}}`, "Create Tenant" saves the ID to `{{tenant_id}}`, "Upload Document" saves the ID to `{{document_id}}`, and "Get Error Detail (Portal)" saves the ID to `{{error_id}}`, so subsequent requests work without manual copy-paste.

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
        'availableTenants'    => fn () => $this->resolveAvailableTenants($request),
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
| Rich text editor | TipTap v3 (vue-3, starter-kit, link, underline) | ✅ Installed |
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
- [x] `App\Auth\` module — models, DTOs, services, controllers
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

**Phase 3 — Frontend & multi-tenancy** *(done)*
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

**Phase 5 — Authentication UX & admin management** *(done)*

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

*5.3 — System settings (admin)* *(done)*
- [x] Admin can manage system-wide settings across seven tabs: Email (SMTP/Postmark/Mailgun/SES), SMS (Twilio/Vonage/SNS), Push (FCM/APNs/OneSignal), Storage (Local/S3/R2/GCS/FTP/SFTP), Authentication (idle timeout, max login attempts), Security (password policy, 2FA, session concurrency), Branding (app name, support contact, logo/favicon)
- [x] Email footer signature (rich-text via TipTap) and unsubscribe URL (CAN-SPAM/GDPR) as global email defaults
- [x] Persistent amber banner on all admin pages when required settings (email driver, auth idle timeout, storage driver) are unset
- [x] Full REST API at `/api/v1/admin/settings` (GET + PUT) for mobile / external clients

*5.4 — App management (admin)* *(done)*
- [x] Admin can edit app information (name, description)

*5.5 — User management (admin)* *(done)*
- [x] Admin can invite a user by email — before sending the invitation the admin selects which apps the user can access and, if the app has tenants, which tenant(s) the user belongs to; the account is inactive until the invitation is accepted
- [x] Admin can edit a user's profile data and app/tenant permissions

*5.6 — Tenant management (admin)* *(done)*
- [x] Admin can add and edit tenant details and database connection settings
- [x] When a new tenant is created, its tenant database migrations run automatically
- [x] Admin can trigger migrations on a selected tenant or across all tenants from the UI
- [x] Admin can toggle a tenant's `is_active` flag — when deactivated, all users with a tenant role on that tenant are force-logged out (tokens revoked)
- [x] Admin can delete a tenant — the tenant's database is dropped and all related central records are removed
- [x] Leaving the Database Connection fields blank auto-fills credentials from `DB_TENANT_*` env vars and generates `db_name` as `tenant_{slug}` for an isolated tenant database
- [x] Creating a new tenant automatically assigns all admin-role users to it in `user_app_tenants`

*5.7 — Tenant settings (admin)* *(done)*
- [x] Admin can add, update, and delete per-tenant settings:
  - **Email service** — SMTP, Postmark, Mailgun, SES; if unset, the system default is used
  - **S3 settings** — S3 or R2; if unset, the system default is used
  - **Report PDF header and footer** — rich-text editor with live A4 preview; optional logo upload
  - **Report server settings** — queue name, timeout (seconds), and Redis connection override; connection dropdown populated from `config/queue.php` redis entries
  - **Branding** — app name, support email, support URL
- [x] The tenant's mail driver and S3 disk are resolved at runtime per request via `TenantSettingsService::resolveMailConfig()` and `resolveS3Config()`
- [x] Report jobs dispatched to the tenant's configured queue, timeout, and Redis connection via `ReportController::resolveReportConfig()`
- [x] Tenant report queue — tenant users view their own report jobs at `/tenant/reports` with live status polling; admin views any tenant's jobs at `/admin/tenants/{tenant}/reports`
- [x] Persistent admin tenant layout — Settings, Users, Reports, and Errors pages share a sticky sub-navigation bar so admins stay in context without returning to the tenant list

*5.8 — Admin dashboard* *(done)*

- [x] **Live stat cards** — real counts from the database: total users (active + pending-invitation), active apps, active tenants, active SSO sessions (live Sanctum personal access tokens)
- [x] **Tenant health summary bar** — `TenantHealthSummaryData` (healthy / warning / critical / maintenance counts); clicking a status badge navigates to `/admin/tenants` pre-filtered to that status
- [x] **Recent users widget** — latest 5 users sorted by `created_at` desc; active/pending-invitation badge; "Edit" links open the edit modal on `/admin/users`
- [x] **Pending invitations list** — collapsible section listing users with `invitation_token` set; one-click "Resend Invitation" re-generates the token and re-queues `UserInvitationMail`
- [x] **Unresolved error log summary** — total unresolved `TenantErrorLog` records across all tenants broken down by severity; link to tenant error list
- [x] **Report queue health widget** — pending/processing/failed counts across all tenants; `failed` count in red; per-tenant breakdown
- [x] **Tenant migration compliance** — up-to-date vs behind counts; behind tenants listed with one-click "Run migrations"
- [x] **Dashboard controller** — `DashboardController` + `DashboardService` + `DashboardStatsData` DTO
- [x] **API parity** — `GET /api/v1/admin/dashboard` via `DashboardApiController` returning full snapshot
- [x] **Tests** — `DashboardWebTest`, `DashboardApiTest`, `DashboardServiceTest`

*5.9 — Tenant maintenance mode* *(done)*

- [x] **Per-tenant maintenance toggle** — admin can enable or disable maintenance mode for any individual tenant from `/admin/tenants`; per-row "Maintenance" / "End Maintenance" buttons in the tenant table
- [x] **Bulk maintenance toggle** — "Maintenance: All On" and "Maintenance: All Off" header buttons apply the state to every tenant at once
- [x] **Force logout on enable** — enabling maintenance revokes all Sanctum tokens for users assigned to that tenant; re-login is blocked until maintenance ends
- [x] **503 enforcement** — `ResolveTenantDatabase` middleware returns `HTTP 503 Service Unavailable` for any API request that resolves to a maintenance tenant
- [x] **App picker filter** — `AppService::loadApps()` excludes maintenance tenants so they do not appear in the tenant picker after login
- [x] **Summary cards** — "In Maintenance" count card added to both the admin tenants page and the admin dashboard
- [x] **`is_maintenance` field** — new boolean column on the `tenants` table (`database/migrations/central/`); `TenantRepositoryData`, `TenantData`, `TenantHealthData`, and `TenantHealthSummaryData` all expose the field
- [x] **API parity** — `PATCH /api/v1/admin/tenants/{tenant}/maintenance` and `PATCH /api/v1/admin/tenants/maintenance/all` via `TenantManagementApiController`
- [x] **Tests** — backend: `TenantManagementWebTest`, `TenantManagementApiTest`, `TenantManagementServiceTest`, `TenantHealthWebTest`, `TenantHealthServiceTest`, `DashboardWebTest`, `DashboardApiTest`, `ResolveTenantDatabaseMiddlewareTest`, `AppServiceTest`; frontend: 11 new Vitest cases in `Admin/Tenants/Index.test.js`

*5.10 — Tenant logged-in users* *(done)*

- [x] **Online count on tenant list** — Users column shows "X total" + live "X online" indicator (green dot when active, grey when zero); powered by `TenantRepository::loggedInUserCountByTenant()` joining `user_app_tenants` with `personal_access_tokens`
- [x] **Session column on tenant users page** — pulsing green "Online" badge for users with active tokens; header subtitle shows online count; dash for offline users
- [x] **Force Logout** — red button visible only for online users; calls `UserRepository::revokeTokensForUser()` to delete all tokens for that user immediately
- [x] **Compact actions layout** — tenant list actions split into two compact rows (navigation links / admin actions) to eliminate horizontal overflow
- [x] **DTO changes** — `TenantData::loggedInCount` (serialises as `logged_in_count`); `UserData::isLoggedIn` (serialises as `is_logged_in`)
- [x] **API parity** — `DELETE /api/v1/admin/tenants/{tenant}/users/{user}/session` via `TenantUsersApiController::forceLogout()`
- [x] **Tests** — backend: 6 new `TenantUsersWebTest`, 6 new `TenantUsersApiTest`, 1 new `TenantManagementWebTest`; frontend: 10 new Vitest cases in `Index.test.js`
- [x] **Postman** — `is_logged_in` added to List Users example; Force Logout User request documented with 200/401/404 responses

**Phase 6 — Tenant app & tenant settings integration** *(planned)*

Phase 6 makes every per-tenant setting configured in Phase 5.7 visible and functional inside the tenant portal. The existing `tenant/` migrations (except `report_subscriptions`) are removed so the tenant database schema starts clean; all tenant-facing data lives through the layered `Controller → Service → Repository → Model` architecture. A fully-featured **Documents** module serves as the canonical sample demonstrating every tenant capability end-to-end.

*6.1 — Tenant migration clean-up* *(done)*
- [x] Remove all existing `database/migrations/tenant/` files except `create_report_subscriptions_table.php`
- [x] Re-run `php artisan tenant:migrate --fresh` to apply the clean schema to all tenant databases

*6.2 — Documents module (sample tenant feature)* *(done)*
- [x] New `app/Documents/` module following the standard module structure (`Data/`, `Http/Controllers/`, `Http/Requests/`, `Routes/`, `Services/`, `Tests/`)
- [x] **Upload documents** — users upload files through the tenant portal; files streamed via `putFileAs()` to the tenant's configured storage disk (resolved via `TenantSettingsService::resolveDisk()`, falls back to system default); metadata persisted to the `documents` tenant table
- [x] **Document list** — paginated list (20/page) with file name, size, upload date, uploader name, and per-row download and delete buttons; rendered in `Documents/Index.vue` using `TenantLayout`
- [x] **Download** — signed temporary URL for S3/R2 disks (5-minute expiry, redirect 302); streamed binary response for local disk; handled via `DocumentService::downloadResponse()`
- [x] **Web routes** — `GET|POST /documents`, `GET /documents/{id}/download`, `DELETE /documents/{id}` (session auth, `ResolveWebTenantDatabase` middleware)
- [x] **API parity** — `GET|POST /api/v1/documents`, `GET /api/v1/documents/{id}`, `GET /api/v1/documents/{id}/download`, `DELETE /api/v1/documents/{id}` (Bearer + `X-App` + `X-Tenant` headers, `ResolveTenantDatabase` middleware)
- [x] **Multi-driver tenant DB middleware** — `ResolveWebTenantDatabase` and `ResolveTenantDatabase` now detect `DB_TENANT_DRIVER` and build the correct connection config (PostgreSQL: `schema`, `sslmode`, utf8; MySQL: utf8mb4, collation, strict); default port falls back to 5432 / 3306 respectively
- [x] **Tests** — `DocumentWebTest` (8), `DocumentApiTest` (6), `DocumentServiceTest` (6) covering upload, list, download, delete; `ResolveWebTenantDatabaseMiddlewareTest` (9 new tests); 4 new pgsql/mysql driver tests in `ResolveTenantDatabaseMiddlewareTest`
- [x] **Documents nav link** added to `TenantLayout.vue` sidebar

*6.3 — Tenant error log portal view* *(done)*
- [x] New pages at `/tenant/errors` (list) and `/tenant/errors/{id}` (detail) — read-only portal view; response DTO omits stack trace, file path, line number, and request headers; support callout on detail page prompts users to quote the error code
- [x] Error list with stat cards, severity/unresolved filters, severity badge, error code link, short exception class, message, status badge, and timestamp
- [x] **Web routes** — `GET /tenant/errors`, `GET /tenant/errors/{id}` (session auth, `ResolveWebTenantDatabase` middleware); named `tenant.errors`, `tenant.errors.show`
- [x] **API parity** — `GET /api/v1/tenant/errors`, `GET /api/v1/tenant/errors/{id}` (Bearer + `X-App` + `X-Tenant` headers); responses follow `{ data: [...] }` / `{ data: {...} }` envelope; named `tenant.api.errors.*`
- [x] **Tests** — `TenantErrorsWebTest` (7), `TenantErrorsApiTest` (7), `TenantErrorsServiceTest` (7) covering list, detail, tenant scoping, auth guard, severity/unresolved filters, cross-tenant 404, and DTO sensitivity mask; `ErrorLogs.test.js` (18 Vitest), `ErrorLog.test.js` (24 Vitest)
- [x] **Error Logs** nav link added to `TenantLayout.vue` sidebar

*6.4 — Tenant report queue enhancements* *(done)*
- [x] Report queue page (`/tenant/reports`) gains a "Generate Report" quick-action button to dispatch a sample report job without leaving the portal
- [x] Report type and format selectable from a modal (type: `documents_summary`; format: `screen` / `pdf` / `excel`); dispatched via `GenerateReportJob` honouring tenant queue/timeout/Redis settings
- [x] Retry failed reports — button shown on `failed` rows resets status to `pending` and re-queues the job; cross-tenant 403 enforced
- [x] Subscription management UI — create, pause, and delete scheduled report subscriptions directly in the tenant portal; `TenantReportSubscriptionService` + `TenantReportSubscriptionController` added
- [x] **Document source tracking** — `DocumentSource` enum (`upload` / `report` / `subscription`); migration adds `source` column to `documents` table; `GenerateReportJob` calls `DocumentService::createFromReport()` after successful PDF/Excel generation; source badge rendered in `Documents/Index.vue`
- [x] **Dedicated `reports` filesystem disk** — `config/filesystems.php` adds `reports` disk rooted at `storage_path('app')` to fix download 404s caused by the Laravel 11 `local` disk root change to `storage/app/private/`
- [x] **Tenant PDF header/footer** — `PdfReportGenerator` reads `reportPdfHeaderText` / `reportPdfFooterText` from `TenantSettingsService` and passes them to the Blade view; rendered as unescaped HTML via `{!! !!}`
- [x] **Container-resolved generators** — `ReportGeneratorFactory` replaced `new GeneratorClass($report)` with `app()->make($class, ['report' => $report])` so injected services are wired automatically
- [x] **Tenant-scoped file paths** — documents stored under `{slug}/documents/`, reports under `{slug}/reports/`
- [x] **API parity** — `POST /api/v1/tenant/reports/quick`, `POST /api/v1/tenant/reports/{id}/retry` (Bearer + `X-App` + `X-Tenant` headers)
- [x] **Tests** — `TenantReportQueueWebTest` (expanded), `TenantReportQueueApiTest` (expanded), `ReportDownloadTest` (updated), `DocumentServiceTest` (5 new), `DocumentWebTest` (2 new), `DocumentApiTest` (2 new), `Documents/Index.test.js` (20 Vitest, new file)

*6.5 — Tenant portal navigation update*
- [ ] `TenantLayout.vue` sidebar updated — adds **Documents** and **Errors** nav items alongside Dashboard and Report Queue
- [ ] Guided page tours added for Documents and Errors pages (driver.js, `useTour` composable)

*6.6 — Sample report seed migration*
- [ ] New central migration `seed_sample_reports` inserts sample `Report` records tied to the default admin user and the Demo Tenant so the queue is pre-populated on a fresh install
- [ ] Two individual reports (one `screen`, one `pdf` format, delivery `download`) are inserted as `pending` and dispatched to Horizon immediately — demonstrating single-job queue flow and PDF regeneration once the PDF package is installed
- [ ] Two additional reports sharing the same `batch_id` are inserted and dispatched together — demonstrating batch dispatch, parallel processing, and ZIP download on completion
- [ ] All four reports use `delivery: download` so they appear on the tenant report queue page and can be re-dispatched from the UI

*6.7 — Tenant switcher (multi-tenant access)* *(done)*
- [x] Users assigned to more than one tenant under the **Tenant** app see a switcher dropdown in the `TenantLayout.vue` sidebar header directly below the tenant name
- [x] New `ResolveWebTenantDatabase` middleware on all web tenant routes — resolves the active tenant from `session('tenant_app_current_tenant')`, falling back to the user's `is_default` tenant in `user_app_tenants`; sets `current_tenant` and `current_app` on request attributes and wires the per-request `tenant` DB connection
- [x] `POST /tenant/switch` route handled by `TenantSwitcherController` — validates the requested tenant slug against the user's permitted tenant list, updates the session, and redirects to `/tenant`
- [x] `HandleInertiaRequests` shares `availableTenants` (`{id, name, slug, isCurrent}[]`) for authenticated tenant-portal pages when the user has more than one accessible tenant
- [x] Switching tenant triggers a full Inertia visit so all page props reflect the new tenant context immediately
- [x] **API** — `GET /api/v1/tenant/tenants` lists accessible tenants; `POST /api/v1/tenant/switch` switches the active tenant for API consumers

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
