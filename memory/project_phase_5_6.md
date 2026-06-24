---
name: project-phase-5-6
description: Phase 5.6 — Tenant Management (admin) — CRUD, migrations, toggle active with token revocation, delete with DB drop
metadata:
  type: project
---

Admin full tenant management at `/admin/tenants` and `/api/v1/admin/tenants/*`.

**Key files:**
- `app/Admin/Data/TenantData.php` — service-layer DTO (includes health fields for combined page)
- `app/Admin/Data/CreateTenantData.php`, `UpdateTenantData.php` — input DTOs
- `app/Admin/Services/TenantManagementService.php` — list (with health), create, update, setActive, delete, runMigrations
- `app/Admin/Http/Controllers/TenantManagementController.php` — Inertia web
- `app/Admin/Http/Controllers/TenantManagementApiController.php` — REST API
- `app/Repositories/Central/TenantRepository.php` — added find, create, update, setActive, delete, dropDatabase, getUserIdsForTenant
- `app/Repositories/Central/UserRepository.php` — added revokeTokensForUsers
- `resources/js/Pages/Admin/Tenants/Index.vue` — rewritten with Add/Edit modals, Toggle, Migrate, Delete actions

**Design decisions:**
- `dropDatabase` wraps the DB DROP in try/catch — best-effort, central records removed even if server is unreachable
- Token revocation (Sanctum) happens on both deactivate and delete; web sessions not invalidated (no per-user session store support without extra infra)
- `tenant:migrate` is called synchronously via `Artisan::call()` on create and runMigrations
- `TenantData` DTO includes health fields (healthStatus, userCount, pendingReports, failedReports) so the management page is also the health dashboard
- Routes: migrate-all defined BEFORE `{tenant}` parameterized routes to avoid ambiguity

**Why:** See [[project-architecture-standards]] for layered architecture rules.
