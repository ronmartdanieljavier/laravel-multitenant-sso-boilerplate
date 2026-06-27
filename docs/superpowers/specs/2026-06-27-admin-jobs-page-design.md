# Admin Jobs Page — Design Spec

**Date:** 2026-06-27

## Overview

Add a jobs/queue monitoring feature to the admin panel with two entry points:

1. **Global jobs page** at `/admin/jobs` — cross-tenant view with tenant + status filters
2. **Per-tenant jobs page** at `/admin/tenants/{tenant}/jobs` — scoped view added to the tenant sub-nav

Both views surface `TenantJobRecord` data (pending, running, completed, failed) with pagination and auto-polling when active jobs exist.

## Architecture

### Service Layer

New `App\Admin\Services\TenantJobsAdminService` in `app/Admin/Services/`:

- `listAll(int $perPage, ?int $tenantId, ?TenantJobStatus $status): LengthAwarePaginator<TenantJobData>` — cross-tenant listing with optional filters
- `listForTenant(int $tenantId, ?TenantJobStatus $status, int $perPage): LengthAwarePaginator<TenantJobData>` — scoped to one tenant

Both methods use `TenantJobRecordRepository` and return `TenantJobData` DTOs (reusing the existing DTO from `App\TenantJobs\Data\TenantJobData`).

### Controllers

`App\Admin\Http\Controllers\TenantJobsController` (web):

- `index(Request $request): Response` — global view at `GET /admin/jobs`
- `indexForTenant(Request $request, Tenant $tenant): Response` — per-tenant view at `GET /admin/tenants/{tenant}/jobs`

`App\Admin\Http\Controllers\TenantJobsApiController` (API):

- `index(Request $request): JsonResponse` — `GET /api/admin/jobs`
- `indexForTenant(Request $request, Tenant $tenant): JsonResponse` — `GET /api/admin/tenants/{tenant}/jobs`

### Routes

**Web** (`app/Admin/Routes/web_admin.php`):
```
GET /admin/jobs                          → admin.jobs
GET /admin/tenants/{tenant}/jobs         → admin.tenants.jobs
```

**API** (`app/Admin/Routes/api_admin.php`):
```
GET /api/admin/jobs                      → api.admin.jobs
GET /api/admin/tenants/{tenant}/jobs     → api.admin.tenants.jobs
```

Both web routes are under `auth` middleware. API routes are under `auth:sanctum`.

## Frontend

### Global Page — `resources/js/Pages/Admin/Jobs/Index.vue`

- Uses `AdminLayout` (main admin sidebar, no tenant sub-nav)
- Filters: tenant dropdown (all tenants list passed as prop) + status tabs (All / Pending / Running / Done / Failed)
- Paginated table columns: Tenant, Job Name, Status badge, Started At, Duration, Created At
- Polls every 4s (`usePoll`) when any job has status `pending` or `running`
- Filter changes use `router.get` with `preserveState: true`

Props from controller:
```js
{
  jobs: Object,        // paginated TenantJobData (includes tenant name)
  tenants: Array,      // [{ id, name }] for the dropdown
  filters: Object,     // { tenant_id, status }
}
```

### Per-Tenant Page — `resources/js/Pages/Admin/Tenants/Jobs.vue`

- Uses `AdminTenantLayout` (tenant sub-nav)
- Status tabs only (no tenant dropdown — already scoped)
- Same table columns minus Tenant column
- Same 4s polling behaviour
- Mirrors the existing `Tenant/Jobs.vue` in admin styling

Props:
```js
{
  tenant: Object,      // { id, name, slug }
  jobs: Object,        // paginated TenantJobData
  filters: Object,     // { status }
}
```

### Layout Update — `AdminTenantLayout.vue`

Add `Jobs` entry to `tenantNav`:
```js
{ label: 'Jobs', href: `/admin/tenants/${id}/jobs` }
```

Positioned after `Errors` (end of nav).

## API Response Shape

Single resource listing:
```json
{
  "data": [...],
  "meta": { "current_page": 1, "last_page": 3, "per_page": 20, "total": 55 }
}
```

## Data Notes

- The existing `TenantJobData` DTO lacks a `tenantName` field. The global view needs tenant context, so either:
  - The repository join is extended to include tenant name, or
  - The service enriches the result by loading tenants separately
- Preferred: extend `TenantJobRecordRepositoryData` with `tenantId` (already present) and let the service load a tenant map to attach names — avoids a JOIN change.
- For the global admin DTO, create `App\Admin\Data\AdminJobData` extending `TenantJobData` with an added `tenantName: string` field.

## Testing

| File | Coverage |
|------|----------|
| `app/Admin/Tests/TenantJobsWebTest.php` | Both web routes, tenant scoping, status filter, pagination |
| `app/Admin/Tests/TenantJobsApiTest.php` | Both API routes, JSON shape, auth |
| `app/Admin/Tests/TenantJobsAdminServiceTest.php` | `listAll()` with/without filters, `listForTenant()`, DTO types |
