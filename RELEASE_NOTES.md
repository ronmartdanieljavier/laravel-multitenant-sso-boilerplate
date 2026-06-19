# Release Notes

---

## [Unreleased] — In Progress

### Planned
- Multi-app monorepo structure (`apps/login`, `apps/admin`, `apps/client`, `apps/reports`)
- `packages/central` shared Composer package (models, middleware, TenantResolver)
- `packages/ui` shared Vue 3 component library
- Dynamic tenant database resolution middleware
- Laravel Horizon + Redis async report queue
- Docker Compose full local dev stack
- PHPStan 2.2 + Larastan at level 8

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
