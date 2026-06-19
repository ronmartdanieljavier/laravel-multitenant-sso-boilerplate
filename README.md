# laravel-multitenant-starter

A production-ready Laravel monorepo template for building multi-tenant SaaS platforms with Single Sign-On, granular per-user permissions, dynamic tenant database resolution across any server, and a scalable async reporting engine.

---

## What's included

| App | URL | Responsibility |
|---|---|---|
| **Login** | `login.domain.com` | SSO via Laravel Passport — issues JWT, shows app picker |
| **Admin** | `admin.domain.com` | Manage users, permissions, client DBs, system settings |
| **Client** | `client.domain.com` | Transactional app — reads and writes to tenant DB |
| **Reports** | `reports.domain.com` | Read-only heavy queries, async generation, replica support |
| **Central package** | `packages/central` | Shared models, TenantResolver, middleware — used by all apps |

---

## Architecture overview

```
┌─────────────────────────────────────────────────────┐
│                  login.domain.com                   │
│         Single login — issues JWT + app list        │
└────────────┬───────────────────┬────────────────────┘
             │                   │
     ┌───────▼──────┐   ┌────────▼────────┐
     │ admin.domain │   │ client / reports │
     │   .com       │   │   .domain.com    │
     └───────┬──────┘   └────────┬─────────┘
             │                   │
     ┌───────▼───────────────────▼─────────┐
     │              Central DB             │
     │  users, apps, clients, permissions  │
     └─────────────────────────────────────┘
                         │
          ┌──────────────┼──────────────┐
          │              │              │
   ┌──────▼──────┐ ┌─────▼──────┐ ┌───▼────────┐
   │client_acme  │ │client_globex│ │client_xyz  │
   │    _db      │ │    _db      │ │   _db      │
   │(any server) │ │(any server) │ │(any server)│
   └─────────────┘ └────────────┘ └────────────┘
```

---

## Key features

**Inertia.js + Vue 3 frontend**
- Each Laravel app serves its own Vue 3 pages via Inertia.js — no separate frontend server
- Vue page components live in `resources/js/Pages/` inside each app, built by Vite
- Active client and user permissions shared to every page automatically via Inertia shared props
- Optional `packages/ui` local package for shared Vue components across all apps

**Single Sign-On**
- One login page for all apps via Laravel Passport (OAuth2)
- JWT carries user identity and per-app client access list
- App picker shown after login — redirect straight through if only one app

**Two-dimensional permissions**
- Control which apps a user can access
- Control which tenant databases a user can access within each app
- Role support per app and per client (`admin`, `user`, `readonly`)

**Dynamic tenant database resolution**
- Each client's DB credentials (host, port, name, username, password) stored in the central DB
- Tenant connection resolved at runtime per request via middleware
- Supports any DB server — AWS RDS, DigitalOcean, GCP, on-premise, or any MySQL/PostgreSQL host
- Multiple tenants can share a server, or each can have their own

**Scalable reporting**
- Separate `reports` app — runs as its own process, never blocks the transactional app
- Report jobs dispatched to a queue, run async via Laravel Horizon
- Per-client read replica support — point heavy queries away from the primary
- Clients can be upgraded to a dedicated report server with zero code changes — just a DB row update

**Admin panel**
- Manage users and assign app + client DB access
- Add new tenant databases and run migrations from the UI
- Test DB connection before saving credentials
- System-wide settings management

---

## Monorepo structure

Each app is a standard Laravel installation. Inertia.js replaces Blade — Vue 3 page components live inside each app under `resources/js/Pages/`, served directly by Laravel via Vite. No separate frontend server or build pipeline needed.

```
laravel-multitenant-starter/
│
├── apps/
│   ├── login/                          # login.domain.com
│   │   ├── app/Http/Controllers/
│   │   │   └── Auth/
│   │   │       ├── LoginController.php
│   │   │       └── AppPickerController.php
│   │   ├── resources/js/
│   │   │   ├── Pages/
│   │   │   │   ├── Auth/Login.vue      # Login form
│   │   │   │   └── AppPicker.vue       # App switcher after login
│   │   │   ├── Layouts/AuthLayout.vue
│   │   │   └── app.js                  # Inertia bootstrap
│   │   └── vite.config.js
│   │
│   ├── admin/                          # admin.domain.com
│   │   ├── app/Http/Controllers/
│   │   │   ├── UserController.php
│   │   │   ├── ClientController.php
│   │   │   └── SettingsController.php
│   │   ├── resources/js/
│   │   │   ├── Pages/
│   │   │   │   ├── Users/
│   │   │   │   │   ├── Index.vue       # User list + permission overview
│   │   │   │   │   └── Edit.vue        # Assign app + client DB access
│   │   │   │   ├── Clients/
│   │   │   │   │   ├── Index.vue       # Tenant DB list
│   │   │   │   │   └── Create.vue      # Provision new tenant
│   │   │   │   └── Settings/Index.vue
│   │   │   ├── Layouts/AdminLayout.vue
│   │   │   └── app.js
│   │   └── vite.config.js
│   │
│   ├── client/                         # client.domain.com
│   │   ├── app/Http/Controllers/
│   │   │   ├── DashboardController.php
│   │   │   └── ClientSwitchController.php
│   │   ├── resources/js/
│   │   │   ├── Pages/
│   │   │   │   ├── Dashboard.vue
│   │   │   │   └── ...                 # domain-specific pages
│   │   │   ├── Components/
│   │   │   │   └── ClientSwitcher.vue  # tenant switcher dropdown
│   │   │   ├── Layouts/AppLayout.vue
│   │   │   └── app.js
│   │   └── vite.config.js
│   │
│   └── reports/                        # reports.domain.com
│       ├── app/Http/Controllers/
│       │   └── ReportController.php
│       ├── app/Jobs/
│       │   └── GenerateReportJob.php
│       ├── resources/js/
│       │   ├── Pages/
│       │   │   ├── Reports/Index.vue   # report list + queue status polling
│       │   │   └── Reports/View.vue    # rendered report viewer
│       │   └── app.js
│       └── vite.config.js
│
├── packages/
│   ├── central/                        # Shared Laravel Composer package
│   │   └── src/
│   │       ├── Models/                 # User, Client, App, UserAppClient
│   │       ├── Middleware/             # VerifyAppAccess, SetTenantDatabase
│   │       └── Services/
│   │           └── TenantResolver.php
│   │
│   └── ui/                            # Shared Vue 3 components (optional)
│       └── src/
│           ├── components/            # Button, Modal, DataTable, Alert
│           └── composables/           # useAuth.js, useTenant.js, useClient.js
│
├── database/
│   └── migrations/
│       ├── central/                   # users, apps, clients, permissions
│       └── tenant/                    # domain tables — run per tenant on provision
│
├── docker/
│   └── php/Dockerfile                 # PHP 8.3 FPM base image (shared)
│
├── docker-compose.yml
├── phpstan.neon                        # root config — extended per app
├── commitlint.config.mjs
├── .husky/
├── CLAUDE.md
└── README.md
```

---

## Central DB schema

```
users               — authentication, core identity
apps                — registered apps (login, admin, client, reports)
clients             — tenant DB credentials (host, port, name, user, pass)
user_apps           — which apps a user can access + role
user_app_clients    — which client DBs a user can access per app + role + default
system_settings     — global config
```

---

## Current state

Laravel 13 is installed at the repo root. The monorepo structure (`apps/`, `packages/`) is planned — the SSO backend is now complete. See [RELEASE_NOTES.md](RELEASE_NOTES.md) for a full changelog.

What's built:
- **Laravel 13.16.1** — framework skeleton at repo root
- **Laravel Boost 2.4** — starter kit scaffolding
- **Laravel Sanctum 4.0** — API token authentication
- **spatie/laravel-data 4.23** — DTOs with snake_case serialization, classes under `App\Login\Data\Core\` using `*CoreData` suffix
- **SSO backend** — login, logout, and app-picker API endpoints under `App\Login\`
- **Central DB schema** — `users`, `apps`, `clients`, `user_apps`, `user_app_clients`, `system_settings` migrations
- **Modular routing** — each app module owns its routes under `app/*/Routes/api_*.php`
- **Collocated tests** — PHPUnit tests live inside each app module (e.g. `app/Login/Tests/`)
- **Inertia.js + Vue 3** — installed and wired up with `HandleInertiaRequests` middleware and dynamic page resolution
- **Frontend landing pages** — dark-themed Vue 3 SFCs for Login, Admin, Client, and Reports at `/login`, `/admin`, `/client`, `/reports`
- **Vitest unit tests** — 16 component tests across all four pages
- **Playwright E2E tests** — browser tests for all four pages against a live Laravel server
- **GitHub Actions CI** — build, unit test, and E2E test jobs on every push and PR
- **Tailwind CSS** via `@tailwindcss/vite`
- **Bunny Fonts** (`Instrument Sans`) via `laravel-vite-plugin`
- **Vite** build pipeline with hot-reload
- **commitlint 21 + Husky 9** — conventional commit enforcement

---

## Getting started

### Requirements

- PHP 8.3+
- Composer
- Node.js 20+
- Docker Engine 29+ and Docker Compose (recommended for local dev)
- MySQL 8.0+ (central DB server)
- Redis (queue backend for Horizon)

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

### Environment variables

Each app has its own `.env`. Key variables:

```bash
# All apps
APP_URL=https://login.domain.com
APP_ID=1                            # matches the id in the apps table
APP_SLUG=login
DB_CONNECTION=central

CENTRAL_DB_HOST=127.0.0.1
CENTRAL_DB_DATABASE=central_db
CENTRAL_DB_USERNAME=central_user
CENTRAL_DB_PASSWORD=secret

SSO_AUTH_SERVER_URL=https://login.domain.com
SSO_LOGIN_URL=https://login.domain.com/login

# Reports app only
REDIS_HOST=127.0.0.1
QUEUE_CONNECTION=redis
```

### Run migrations

```bash
# Central DB migrations
php artisan migrate --path=database/migrations/central

# Provision a new tenant (creates DB + runs tenant migrations)
php artisan tenant:provision "Acme Corp" --host=db1.internal --user=acme --password=secret
```

### Run queue workers

```bash
# In the reports app directory
php artisan horizon
```

---

## Docker local development

A `docker-compose.yml` is included at the repo root to spin up the full stack locally — all four apps, MySQL, and Redis — with a single command.

```bash
docker compose up -d
```

Services exposed:

| Service | URL / Port |
|---|---|
| login app | http://localhost:8001 |
| admin app | http://localhost:8002 |
| client app | http://localhost:8003 |
| reports app | http://localhost:8004 |
| MySQL (central) | localhost:3306 |
| Redis | localhost:6379 |
| Horizon dashboard | http://localhost:8004/horizon |

Each app container runs PHP-FPM 8.3 + Nginx. The reports container additionally runs a Horizon worker process via Supervisor.

```bash
# Rebuild containers after Dockerfile changes
docker compose build --no-cache

# Run artisan commands inside a container
docker compose exec login php artisan migrate

# Tail logs for all services
docker compose logs -f
```

---

## Inertia.js + Vue 3

Each app uses Inertia.js as the glue between Laravel controllers and Vue 3 page components. Controllers return `Inertia::render()` instead of JSON or Blade views — data is injected directly into the Vue page as props.

```php
// app/Http/Controllers/DashboardController.php
public function index(): Response
{
    return Inertia::render('Dashboard', [
        'stats' => DashboardService::stats(),
    ]);
}
```

```vue
<!-- resources/js/Pages/Dashboard.vue -->
<script setup>
defineProps({ stats: Object })
</script>

<template>
  <AppLayout>
    <div>{{ stats.total_orders }}</div>
  </AppLayout>
</template>
```

### Shared props — active client available on every page

The active client and user permissions are shared globally via `HandleInertiaRequests` middleware so every Vue page gets them without an extra API call:

```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'auth' => [
            'user'          => $request->user(),
            'active_client' => session('active_client_id'),
            'my_clients'    => session('app_access'),
        ],
    ]);
}
```

Any Vue page or component reads it via `usePage()`:

```js
import { usePage } from '@inertiajs/vue3'

const { auth } = usePage().props
// auth.active_client, auth.my_clients, auth.user
```

### Shared UI components — packages/ui

Common Vue components (buttons, modals, tables) used across multiple apps live in `packages/ui` and are imported as a local npm package:

```js
// In any app's package.json
{
  "dependencies": {
    "@starter/ui": "file:../../packages/ui"
  }
}

// In any Vue component
import { AppButton, DataTable, ClientSwitcher } from '@starter/ui'
```

---

## Static analysis — PHPStan

PHPStan 2.2 with Larastan is configured at **level 8** across all apps. Run from any app directory:

```bash
composer analyse
# or directly:
./vendor/bin/phpstan analyse --level=8
```

A shared `phpstan.neon` lives at the repo root and is extended per app:

```neon
# phpstan.neon (root)
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    level: 8
    paths:
        - app
    excludePaths:
        - app/Http/Middleware/TrustProxies.php

# apps/login/phpstan.neon
includes:
    - ../../phpstan.neon
```

PHPStan also runs in CI on every pull request via GitHub Actions.

---

## Commit linting — commitlint

commitlint 21 with Husky enforces conventional commits on every commit across the monorepo.

```bash
# Install from repo root
npm install

# Husky is set up automatically via prepare script
# Test a commit message manually
echo "feat(auth): add passkey login support" | npx commitlint
```

Config at repo root (`commitlint.config.mjs`):

```js
export default {
  extends: ['@commitlint/config-conventional'],
  rules: {
    'type-enum': [2, 'always', [
      'feat', 'fix', 'docs', 'style', 'refactor',
      'perf', 'test', 'chore', 'revert', 'ci', 'build',
    ]],
    'scope-case': [2, 'always', 'kebab-case'],
    'subject-max-length': [2, 'always', 100],
  },
};
```

Valid commit examples:

```bash
git commit -m "feat(auth): add SSO token refresh flow"
git commit -m "fix(tenant): resolve DB connection leak on client switch"
git commit -m "docs: update provisioning guide in README"
git commit -m "chore(docker): add redis healthcheck to compose file"
```

---

## Claude Code

This project is built with [Claude Code](https://claude.ai/code) — Anthropic's agentic coding tool — as part of the development workflow.

A `CLAUDE.md` file at the repo root gives Claude Code context about the monorepo structure, conventions, and per-app responsibilities so it can assist across all four apps effectively.

```bash
# Install Claude Code globally
npm install -g @anthropic-ai/claude-code

# Run from the repo root
claude
```

Claude Code is used for:
- Scaffolding new tenant migrations and models
- Generating boilerplate for new apps added to the monorepo
- Reviewing PHPStan errors and suggesting type-safe fixes
- Writing and refactoring report queries against tenant DB connections

---

## Provisioning a new tenant

From the admin panel UI, or via artisan:

```bash
php artisan tenant:provision "Client Name" \
  --host=db1.internal \
  --port=3306 \
  --user=db_user \
  --password=db_pass
```

This will:
1. Save credentials to the `clients` table in the central DB
2. Create the physical database on the target server
3. Run all tenant migrations against the new database

---

## Scaling a tenant to a dedicated report server

When a client outgrows the shared report server:

1. Deploy `apps/reports` to a new server (Forge, Docker, or manual)
2. Update the client row in the central DB:

```sql
UPDATE clients
SET report_db_host = 'acme-replica.rds.amazonaws.com',
    report_db_name = 'acme_report_db',
    report_url     = 'https://acme.reports.domain.com',
    report_server  = 'dedicated'
WHERE slug = 'acme';
```

No code changes required. The login app reads `report_url` and redirects there automatically.

---

## Security

- Tenant DB passwords encrypted at rest using Laravel's `encrypted` cast (AES-256)
- Report app enforces `READ ONLY` MySQL transactions at the PDO level — writes rejected at the DB driver
- All tenant DB access validated against `user_app_clients` on every request
- Permissions loaded into JWT at login — no central DB hit on every request in client/report apps

---

## Tech stack

| Layer | Package | Status |
|---|---|---|
| Framework | Laravel 13.16.1 | ✅ Installed |
| Starter kits | Laravel Boost 2.4 | ✅ Installed |
| API auth | Laravel Sanctum 4.0 | ✅ Installed |
| DTOs | spatie/laravel-data 4.23 | ✅ Installed |
| SSO backend | Login, logout, app-picker API | ✅ Built |
| SSO / OAuth2 | Laravel Passport | Planned |
| Frontend | Vue 3 + Inertia.js | ✅ Installed |
| Frontend pages | Login, Admin, Client, Reports landing pages | ✅ Built |
| CSS | Tailwind CSS (`@tailwindcss/vite`) | ✅ Installed |
| Fonts | Bunny Fonts — Instrument Sans | ✅ Installed |
| Build | Vite + `laravel-vite-plugin` | ✅ Installed |
| Unit tests | Vitest 4 + Vue Test Utils 2 | ✅ Installed |
| E2E tests | Playwright 1.61 (Chromium) | ✅ Installed |
| Queue | Laravel Horizon + Redis | Planned |
| Shared PHP | `packages/central` (local Composer) | Planned |
| Shared Vue | `packages/ui` (local npm) | Planned |
| DB | MySQL 8 (central + tenant), PostgreSQL compatible | Planned |
| Containers | Docker Engine 29 + Docker Compose | Planned |
| Static analysis | PHPStan 2.2 + Larastan (level 8) | Planned |
| Commit linting | commitlint 21 + Husky 9 | ✅ Installed |
| CI | GitHub Actions (build, unit, E2E) | ✅ Active |
| AI coding | Claude Code (Anthropic) | ✅ Active |

---

## Roadmap

**Phase 1 — Foundation** *(done)*
- [x] Laravel 13 installed at repo root
- [x] Laravel Boost + Sanctum + Tailwind CSS + Vite
- [x] commitlint + Husky conventional commits
- [x] Claude Code CLAUDE.md integration

**Phase 2 — SSO backend** *(done)*
- [x] Central DB schema — users, apps, clients, user_apps, user_app_clients, system_settings
- [x] `App\Login\` module — models, DTOs (spatie/laravel-data), actions, controllers
- [x] SSO API — `POST /api/login`, `POST /api/logout`, `GET /api/apps`
- [x] Sanctum token with per-app abilities embedded as token scopes
- [x] Modular routing — each app owns `app/*/Routes/api_*.php`
- [x] Collocated PHPUnit tests — `app/Login/Tests/` registered as `Login` suite

**Phase 3 — Monorepo structure**
- [ ] Split into `apps/login`, `apps/admin`, `apps/client`, `apps/reports`
- [ ] `packages/central` shared Composer package
- [ ] `packages/ui` shared Vue 3 component library
- [ ] Docker Compose full local dev stack
- [ ] PHPStan 2.2 + Larastan static analysis

**Phase 3 — Monorepo structure**
- [ ] Split into `apps/login`, `apps/admin`, `apps/client`, `apps/reports`
- [ ] `packages/central` shared Composer package
- [ ] `packages/ui` shared Vue 3 component library
- [ ] Docker Compose full local dev stack
- [ ] PHPStan 2.2 + Larastan static analysis

**Phase 4 — Frontend & multi-tenancy** *(in progress)*
- [x] Inertia.js + Vue 3 installed and configured
- [x] Landing pages for Login, Admin, Client, and Reports apps
- [x] Vitest unit tests for all four page components
- [x] Playwright E2E tests for all four pages
- [x] GitHub Actions CI — frontend build, unit, and E2E jobs
- [ ] Dynamic tenant database resolution middleware
- [ ] Two-dimensional permissions (app + client DB)
- [ ] Inertia shared props — active client + permissions on every page

**Phase 5 — Reporting & ops**
- [ ] Laravel Horizon + Redis async report queue
- [ ] Per-tenant read replica support
- [ ] Tenant migration version tracking
- [ ] Per-tenant scheduled report subscriptions (email/S3 delivery)
- [ ] Tenant health dashboard in admin

---

## License

MIT — Ron Mart Daniel Javier