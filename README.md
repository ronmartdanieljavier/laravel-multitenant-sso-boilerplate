# laravel-multitenant-sso-boilerplate

A production-ready Laravel boilerplate for building multi-tenant SaaS platforms with Single Sign-On, granular per-user permissions, dynamic tenant database resolution, and a scalable async reporting engine.

---

## What's included

| Module | Route prefix | Responsibility |
|---|---|---|
| **Auth** | `/api/login`, `/api/logout`, `/api/apps` | SSO — issues Sanctum token, returns app + tenant access list |
| **Admin** | `/api/admin/...` | Manage users, permissions, tenant DBs, system settings |
| **Tenant** | `/api/tenant/...` | Transactional app — reads and writes to tenant DB |
| **Reports** | `/api/reports/...` | Read-only heavy queries, async generation, replica support |

---

## Architecture overview

```
┌─────────────────────────────────────────────────────┐
│                   /api/login                        │
│        Single login — issues Sanctum token          │
└────────────┬───────────────────┬────────────────────┘
             │                   │
     ┌───────▼──────┐   ┌────────▼──────────┐
     │  /api/admin  │   │ /api/tenant        │
     │              │   │ /api/reports       │
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

**Scalable reporting**
- Separate `Reports` module — async report generation via Laravel queues
- Per-tenant read replica support — point heavy queries away from the primary

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
│   │   └── Routes/
│   │       └── api_admin.php               # Admin API routes
│   │
│   ├── Auth/
│   │   ├── Actions/
│   │   │   ├── LoginAction.php
│   │   │   └── LoadUserAppsAction.php
│   │   ├── Data/Core/                      # spatie/laravel-data DTOs
│   │   │   ├── AppAccessCoreData.php
│   │   │   ├── AuthTokenCoreData.php
│   │   │   ├── LoginCredentialsCoreData.php
│   │   │   ├── TenantAccessCoreData.php
│   │   │   └── UserCoreData.php
│   │   ├── Enums/
│   │   │   └── Role.php
│   │   ├── Http/Controllers/Auth/
│   │   │   ├── AppPickerController.php
│   │   │   └── LoginController.php
│   │   ├── Http/Requests/
│   │   │   └── LoginRequest.php
│   │   ├── Models/
│   │   │   ├── App.php
│   │   │   ├── SystemSetting.php
│   │   │   ├── Tenant.php
│   │   │   ├── User.php
│   │   │   ├── UserApp.php
│   │   │   └── UserAppTenant.php
│   │   ├── Routes/
│   │   │   └── api_login.php               # SSO API routes
│   │   └── Tests/
│   │       ├── LoginTest.php
│   │       └── AppPickerTest.php
│   │
│   ├── Console/Commands/
│   │   ├── CentralMigrateCommand.php       # php artisan central:migrate
│   │   └── TenantMigrateCommand.php        # php artisan tenant:migrate
│   │
│   ├── Http/
│   │   ├── Controllers/Controller.php
│   │   └── Middleware/HandleInertiaRequests.php
│   │
│   ├── Providers/AppServiceProvider.php
│   │
│   ├── Reports/
│   │   └── Routes/
│   │       └── api_reports.php             # Reports API routes
│   │
│   └── Tenant/
│       └── Routes/
│           └── api_tenant.php              # Tenant API routes
│
├── database/
│   ├── factories/
│   │   ├── Auth/                           # Auth model factories
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
│   │   │   └── create_system_settings_table.php
│   │   └── tenant/                         # Per-tenant migrations (empty — add domain tables here)
│   └── seeders/DatabaseSeeder.php
│
├── resources/
│   ├── js/
│   │   ├── Pages/
│   │   │   ├── Admin/Index.vue             # Admin landing page
│   │   │   ├── Admin/Index.test.js
│   │   │   ├── Login/Index.vue             # Login landing page
│   │   │   ├── Login/Index.test.js
│   │   │   ├── Reports/Index.vue           # Reports landing page
│   │   │   ├── Reports/Index.test.js
│   │   │   ├── Tenant/Index.vue            # Tenant portal landing page
│   │   │   └── Tenant/Index.test.js
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
│   ├── api.php                             # Loads all app/*/Routes/ files
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
users               — authentication, core identity
apps                — registered apps (login, admin, tenant, reports)
tenants             — tenant DB credentials (host, port, name, user, pass)
user_apps           — which apps a user can access + role
user_app_tenants    — which tenant DBs a user can access per app + role + default
system_settings     — global config
```

---

## Tenant DB schema

Each tenant database is provisioned with the following tables. They reference the central `users.id` but contain no user identity data — that lives in central.

```
audit_logs          — tenant-scoped activity trail (user_id, action, subject_type, subject_id, metadata, ip_address)
posts               — tenant content (user_id, title, body, status, published_at)
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
- **Central DB schema** — `users`, `apps`, `tenants`, `user_apps`, `user_app_tenants`, `system_settings` in `database/migrations/central/`
- **Separated migrations** — `database/migrations/central/` and `database/migrations/tenant/` with custom `php artisan central:migrate` and `php artisan tenant:migrate` commands
- **Modular routing** — each module owns its routes under `app/*/Routes/api_*.php`
- **Collocated tests** — PHPUnit tests live inside each module (e.g. `app/Auth/Tests/`)
- **Inertia.js + Vue 3** — installed and wired up with `HandleInertiaRequests` middleware
- **Frontend landing pages** — dark-themed Vue 3 SFCs for Login, Admin, Tenant, and Reports at `/login`, `/admin`, `/tenant`, `/reports`
- **Vitest unit tests** — component tests for all four page components
- **Playwright E2E tests** — browser tests for all four pages against a live Laravel server
- **GitHub Actions CI** — build, unit test, and E2E test jobs on every push and PR
- **Tailwind CSS** via `@tailwindcss/vite`
- **Bunny Fonts** (`Instrument Sans`) via `laravel-vite-plugin`
- **commitlint 21 + Husky 9** — conventional commit enforcement
- **Postman collection** — all SSO endpoints documented and ready to import
- **Docker Compose** — full local dev stack (Nginx + PHP-FPM + PostgreSQL + Redis)

---

## API — Postman collection

A Postman collection is included at [`postman/laravel-multitenant-sso.postman_collection.json`](postman/laravel-multitenant-sso.postman_collection.json).

Import via **Postman → Import → File**. The collection uses two variables — `base_url` (default `http://localhost`) and `token` — and covers all SSO endpoints:

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `POST` | `/api/login` | — | Authenticate and receive a Bearer token + app list |
| `POST` | `/api/logout` | Bearer | Revoke the current token |
| `GET` | `/api/apps` | Bearer | List accessible apps and tenant clients |

The Login request includes a test script that automatically saves the returned token to `{{token}}` so subsequent requests work without manual copy-paste.

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

### Run migrations

```bash
# Central DB only
php artisan central:migrate

# All tenant DBs (reads credentials from the tenants table)
php artisan tenant:migrate

# Single tenant
php artisan tenant:migrate --tenant=acme

# Both central and tenant (default migrate)
php artisan migrate
```

Additional flags available on all three commands: `--fresh`, `--seed`, `--rollback`, `--step`, `--force`.

### Environment variables

Key variables in `.env`:

```bash
APP_URL=http://localhost

DB_CONNECTION=sqlite          # or pgsql for production
CENTRAL_DB_HOST=127.0.0.1
CENTRAL_DB_DATABASE=central_db
CENTRAL_DB_USERNAME=central_user
CENTRAL_DB_PASSWORD=secret

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

# Run migrations
docker compose exec php php artisan migrate --seed
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
    return array_merge(parent::share($request), [
        'auth' => [
            'user'           => $request->user(),
            'active_tenant'  => session('active_tenant_id'),
            'my_tenants'     => session('app_access'),
        ],
    ]);
}
```

---

## Static analysis — PHPStan

PHPStan is configured in `phpstan.neon`:

```bash
./vendor/bin/phpstan analyse --level=8
```

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
| Containers | Docker Engine 29 + Docker Compose | ✅ Configured |
| Commit linting | commitlint 21 + Husky 9 | ✅ Installed |
| CI | GitHub Actions (build, unit, E2E) | ✅ Active |
| API client | Postman collection | ✅ Included |
| AI coding | Claude Code (Anthropic) | ✅ Active |
| Queue | Laravel Horizon + Redis | Planned |
| Static analysis | PHPStan + Larastan (level 8) | Planned |
| Tenant middleware | Dynamic DB resolution | Planned |
| Two-dimensional permissions | App + tenant DB | Planned |

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
- [x] `php artisan central:migrate` and `php artisan tenant:migrate` custom commands
- [x] Migration structure — `database/migrations/central/` and `database/migrations/tenant/` separated
- [x] `php artisan central:migrate` — runs central DB migrations only
- [x] `php artisan tenant:migrate` — runs tenant migrations across all tenant databases (dynamic connection per tenant row)
- [x] Tenant DB schema — `audit_logs` and `posts` tables as baseline tenant data

**Phase 3 — Monorepo structure**
- [ ] Split into `apps/login`, `apps/admin`, `apps/client`, `apps/reports`
- [ ] `packages/central` shared Composer package
- [ ] `packages/ui` shared Vue 3 component library
- [x] Docker Compose full local dev stack (Nginx + PHP-FPM + PostgreSQL + Redis)

**Phase 3 — Frontend & multi-tenancy** *(in progress)*
- [x] Inertia.js + Vue 3 installed and configured
- [x] Landing pages for Login, Admin, Tenant, and Reports modules
- [x] Vitest unit tests for all four page components
- [x] Playwright E2E tests for all four pages
- [x] GitHub Actions CI — frontend build, unit, and E2E jobs
- [ ] Dynamic tenant database resolution middleware
- [ ] Two-dimensional permissions (app + tenant DB)
- [ ] Inertia shared props — active tenant + permissions on every page

**Phase 4 — Reporting & ops**
- [ ] Laravel Horizon + Redis async report queue
- [ ] Per-tenant read replica support
- [ ] Tenant migration version tracking
- [ ] Per-tenant scheduled report subscriptions (email/S3 delivery)
- [ ] Tenant health dashboard in admin
- [ ] PHPStan + Larastan static analysis

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
