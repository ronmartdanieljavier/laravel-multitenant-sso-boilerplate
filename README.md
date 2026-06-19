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

- PHP 8.2+
- Composer
- Node.js 20+
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

- **Framework** — Laravel 11
- **Auth** — Laravel Passport (OAuth2 / JWT)
- **Queue** — Laravel Horizon + Redis
- **Shared code** — Local Composer package (`packages/central`)
- **Frontend** — Vue 3 + Inertia.js (per app)
- **DB** — MySQL 8 (central + tenant), PostgreSQL compatible

---

## Roadmap

- [ ] Filament admin panel integration
- [ ] Tenant migration version tracking
- [ ] Per-tenant scheduled report subscriptions (email/S3 delivery)
- [ ] Docker Compose local development setup
- [ ] GitHub Actions CI/CD pipeline
- [ ] Tenant health dashboard in admin

---

## License

Ron Mart Daniel Javier