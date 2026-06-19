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

```
/
├── apps/
│   ├── login/          # login.domain.com
│   ├── admin/          # admin.domain.com
│   ├── client/         # client.domain.com
│   └── reports/        # reports.domain.com
├── packages/
│   └── central/        # shared package — models, middleware, TenantResolver
└── database/
    └── migrations/
        ├── central/    # users, apps, clients, permissions
        └── tenant/     # orders, products — runs per tenant on provision
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
git clone https://github.com/your-username/laravel-multitenant-starter.git
cd laravel-multitenant-starter
```

Set up each app:

```bash
# Example for the login app
cd apps/login
cp .env.example .env
composer install
php artisan key:generate
php artisan passport:install
```

Repeat for `admin`, `client`, and `reports`.

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

- **Framework** — Laravel 13
- **Auth** — Laravel Passport (OAuth2 / JWT)
- **Queue** — Laravel Horizon + Redis
- **Shared code** — Local Composer package (`packages/central`)
- **Frontend** — Vue 3 + Inertia.js (per app)
- **DB** — MySQL 8 (central + tenant), PostgreSQL compatible
- **Containers** — Docker Engine 29 + Docker Compose (full local dev stack)
- **Static analysis** — PHPStan 2.2 + Larastan (level 8 by default)
- **Commit linting** — commitlint 21 with `@commitlint/config-conventional` + Husky
- **AI coding** — Claude Code (Anthropic) for agentic development workflows

---

## Roadmap

- [x] Docker Compose local development setup
- [x] PHPStan 2.2 + Larastan static analysis
- [x] commitlint + Husky conventional commits
- [x] Claude Code CLAUDE.md integration
- [ ] Filament admin panel integration
- [ ] Tenant migration version tracking
- [ ] Per-tenant scheduled report subscriptions (email/S3 delivery)
- [ ] GitHub Actions CI/CD pipeline
- [ ] Tenant health dashboard in admin

---

## License

MIT