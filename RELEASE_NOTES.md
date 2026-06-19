# Release Notes

---

## [Unreleased] — In Progress

### Added
- Laravel 13.16.1 installed at repo root (PHP 8.3+)
- Laravel Boost 2.4 for starter kit scaffolding
- Laravel Sanctum 4.0 for API token authentication
- Tailwind CSS via `@tailwindcss/vite` plugin
- Bunny Fonts (`Instrument Sans`) via `laravel-vite-plugin`
- Vite build pipeline with hot-reload and storage view exclusion
- commitlint 21 + Husky 9 enforcing conventional commits across the repo
- `CLAUDE.md` with Claude Code context for agentic development workflows

### Planned
- Inertia.js + Vue 3 frontend per app
- Laravel Passport (OAuth2 / JWT) for SSO
- Multi-app monorepo structure (`apps/login`, `apps/admin`, `apps/client`, `apps/reports`)
- `packages/central` shared Composer package (models, middleware, TenantResolver)
- `packages/ui` shared Vue 3 component library
- Dynamic tenant database resolution middleware
- Laravel Horizon + Redis async report queue
- Docker Compose full local dev stack
- PHPStan 2.2 + Larastan at level 8
- GitHub Actions CI/CD pipeline

---

## [0.1.0] — 2026-06-19

### Added
- Initial repository setup
- Project README with full architecture vision (monorepo, SSO, multi-tenant)
- commitlint configuration (`commitlint.config.js`) with conventional commit rules
- Husky pre-commit and commit-msg hooks
- `.gitignore` for Laravel and Node.js artifacts
