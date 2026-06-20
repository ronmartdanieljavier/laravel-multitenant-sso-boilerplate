# Release Notes

---

## [Unreleased] — In Progress

### Planned
- Multi-app monorepo structure (`apps/login`, `apps/admin`, `apps/client`, `apps/reports`)
- `packages/central` shared Composer package (models, middleware, TenantResolver)
- `packages/ui` shared Vue 3 component library
- Dynamic tenant database resolution middleware
- Laravel Horizon + Redis async report queue
- PHPStan 2.2 + Larastan at level 8

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
