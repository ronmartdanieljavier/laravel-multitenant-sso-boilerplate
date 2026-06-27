# Admin Jobs Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a global `/admin/jobs` page (filterable by tenant + status) and a per-tenant `/admin/tenants/{tenant}/jobs` sub-page (added to the tenant sub-nav) so admins can monitor background job activity across the platform.

**Architecture:** A new `TenantJobsAdminService` wraps the existing `TenantJobRecordRepository`, adding a `listAll()` method for cross-tenant queries and reusing `listForTenant()` for scoped views. A new `AdminJobData` DTO extends `TenantJobData` with a `tenantName` field for the global view. Two controller pairs (web + API) handle both routes.

**Tech Stack:** Laravel 13, Inertia v3, Vue 3, PHPUnit 12, Spatie LaravelData, TailwindCSS.

## Global Constraints

- PHP 8.5 — use constructor property promotion, explicit return types, type hints everywhere.
- All DTOs extend `Spatie\LaravelData\Data` with camelCase property names.
- Controller → Service → Repository only. No direct Eloquent in controllers or services.
- Every web route has an API counterpart under `auth:sanctum`.
- All tests use `LazilyRefreshDatabase` and scope assertions to records created in that test.
- Run `vendor/bin/pint --dirty --format agent` after every PHP file change.
- Run `php artisan test --compact` with a filter after each task.
- Vue components must have a single root element.

---

### Task 1: AdminJobData DTO + TenantJobsAdminService

**Files:**
- Create: `app/Admin/Data/AdminJobData.php`
- Create: `app/Admin/Services/TenantJobsAdminService.php`
- Modify: `app/Repositories/Central/TenantJobRecordRepository.php` — add `listAll()` method

**Interfaces:**
- Produces:
  - `AdminJobData` — same fields as `TenantJobData` plus `tenantName: string`
  - `TenantJobsAdminService::listAll(int $perPage, ?int $tenantId, ?TenantJobStatus $status): LengthAwarePaginator<int, AdminJobData>`
  - `TenantJobsAdminService::listForTenant(int $tenantId, ?TenantJobStatus $status, int $perPage): LengthAwarePaginator<int, TenantJobData>`
  - `TenantJobRecordRepository::listAll(?int $tenantId, ?TenantJobStatus $status, int $perPage): LengthAwarePaginator<int, TenantJobRecordRepositoryData>`

- [ ] **Step 1: Write the failing service test**

Create `app/Admin/Tests/TenantJobsAdminServiceTest.php`:

```php
<?php

namespace App\Admin\Tests;

use App\Admin\Data\AdminJobData;
use App\Admin\Services\TenantJobsAdminService;
use App\Models\Central\Tenant;
use App\Models\Central\TenantJobRecord;
use App\TenantJobs\Data\TenantJobData;
use App\TenantJobs\Enums\TenantJobStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantJobsAdminServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private TenantJobsAdminService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TenantJobsAdminService::class);
    }

    private function makeJob(Tenant $tenant, array $overrides = []): TenantJobRecord
    {
        return TenantJobRecord::create(array_merge([
            'id' => (string) Str::uuid(),
            'tracking_id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'job_class' => 'App\\Jobs\\SomeJob',
            'display_name' => 'Some Job',
            'status' => TenantJobStatus::Pending,
        ], $overrides));
    }

    public function test_list_all_returns_admin_job_data_dtos(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Acme Corp']);
        $this->makeJob($tenant);

        $result = $this->service->listAll(20, null, null);

        $this->assertCount(1, $result->items());
        $this->assertInstanceOf(AdminJobData::class, $result->items()[0]);
        $this->assertSame('Acme Corp', $result->items()[0]->tenantName);
    }

    public function test_list_all_filters_by_tenant(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        $this->makeJob($tenant1);
        $this->makeJob($tenant2);

        $result = $this->service->listAll(20, $tenant1->id, null);

        $this->assertCount(1, $result->items());
        $this->assertSame($tenant1->id, $result->items()[0]->tenantId);
    }

    public function test_list_all_filters_by_status(): void
    {
        $tenant = Tenant::factory()->create();
        $this->makeJob($tenant, ['status' => TenantJobStatus::Completed]);
        $this->makeJob($tenant, ['status' => TenantJobStatus::Failed]);

        $result = $this->service->listAll(20, null, TenantJobStatus::Failed);

        $this->assertCount(1, $result->items());
        $this->assertSame(TenantJobStatus::Failed, $result->items()[0]->status);
    }

    public function test_list_for_tenant_returns_tenant_job_data_dtos(): void
    {
        $tenant = Tenant::factory()->create();
        $this->makeJob($tenant);

        $result = $this->service->listForTenant($tenant->id, null, 20);

        $this->assertCount(1, $result->items());
        $this->assertInstanceOf(TenantJobData::class, $result->items()[0]);
    }

    public function test_list_for_tenant_scopes_to_tenant(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        $this->makeJob($tenant1);
        $this->makeJob($tenant2);

        $result = $this->service->listForTenant($tenant1->id, null, 20);

        $this->assertCount(1, $result->items());
    }

    public function test_duration_seconds_is_computed(): void
    {
        $tenant = Tenant::factory()->create();
        $this->makeJob($tenant, [
            'status' => TenantJobStatus::Completed,
            'started_at' => now()->subSeconds(90),
            'finished_at' => now(),
        ]);

        $result = $this->service->listForTenant($tenant->id, null, 20);

        $this->assertSame(90, $result->items()[0]->durationSeconds);
    }
}
```

- [ ] **Step 2: Run the test to confirm it fails**

```
php artisan test --compact --filter=TenantJobsAdminServiceTest
```

Expected: FAIL — class `TenantJobsAdminService` not found.

- [ ] **Step 3: Add `listAll()` to the repository**

In `app/Repositories/Central/TenantJobRecordRepository.php`, add this method after `listForTenant()`:

```php
/**
 * @return LengthAwarePaginator<int, TenantJobRecordRepositoryData>
 */
public function listAll(
    ?int $tenantId = null,
    ?TenantJobStatus $status = null,
    int $perPage = 20,
): LengthAwarePaginator {
    return $this->model->query()
        ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
        ->when($status, fn ($q) => $q->where('status', $status))
        ->latest()
        ->paginate($perPage)
        ->through(fn (TenantJobRecord $record) => TenantJobRecordRepositoryData::from($record));
}
```

- [ ] **Step 4: Create `app/Admin/Data/AdminJobData.php`**

```php
<?php

namespace App\Admin\Data;

use App\TenantJobs\Data\TenantJobData;
use App\TenantJobs\Enums\TenantJobStatus;
use Carbon\Carbon;
use Spatie\LaravelData\Data;

class AdminJobData extends Data
{
    public function __construct(
        public string $id,
        public int $tenantId,
        public string $tenantName,
        public string $jobClass,
        public string $displayName,
        public TenantJobStatus $status,
        public ?string $errorMessage,
        public ?Carbon $startedAt,
        public ?Carbon $finishedAt,
        public ?Carbon $createdAt,
        public ?int $durationSeconds,
    ) {}
}
```

- [ ] **Step 5: Create `app/Admin/Services/TenantJobsAdminService.php`**

```php
<?php

namespace App\Admin\Services;

use App\Admin\Data\AdminJobData;
use App\Data\Repositories\Central\TenantJobRecordRepositoryData;
use App\Models\Central\Tenant;
use App\Repositories\Central\TenantJobRecordRepository;
use App\TenantJobs\Data\TenantJobData;
use App\TenantJobs\Enums\TenantJobStatus;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TenantJobsAdminService
{
    public function __construct(
        private readonly TenantJobRecordRepository $repository,
    ) {}

    /**
     * @return LengthAwarePaginator<int, AdminJobData>
     */
    public function listAll(
        int $perPage = 20,
        ?int $tenantId = null,
        ?TenantJobStatus $status = null,
    ): LengthAwarePaginator {
        $tenantNames = Tenant::query()
            ->pluck('name', 'id');

        return $this->repository
            ->listAll($tenantId, $status, $perPage)
            ->through(fn (TenantJobRecordRepositoryData $dto) => $this->toAdminData($dto, $tenantNames));
    }

    /**
     * @return LengthAwarePaginator<int, TenantJobData>
     */
    public function listForTenant(
        int $tenantId,
        ?TenantJobStatus $status = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return $this->repository
            ->listForTenant($tenantId, $status, $perPage)
            ->through(fn (TenantJobRecordRepositoryData $dto) => $this->toData($dto));
    }

    private function toAdminData(TenantJobRecordRepositoryData $dto, Collection $tenantNames): AdminJobData
    {
        return new AdminJobData(
            id: $dto->id,
            tenantId: $dto->tenantId,
            tenantName: $tenantNames->get($dto->tenantId, 'Unknown'),
            jobClass: $dto->jobClass,
            displayName: $dto->displayName,
            status: $dto->status,
            errorMessage: $dto->errorMessage,
            startedAt: $dto->startedAt,
            finishedAt: $dto->finishedAt,
            createdAt: $dto->createdAt,
            durationSeconds: $this->computeDuration($dto),
        );
    }

    private function toData(TenantJobRecordRepositoryData $dto): TenantJobData
    {
        return new TenantJobData(
            id: $dto->id,
            jobClass: $dto->jobClass,
            displayName: $dto->displayName,
            status: $dto->status,
            errorMessage: $dto->errorMessage,
            startedAt: $dto->startedAt,
            finishedAt: $dto->finishedAt,
            createdAt: $dto->createdAt,
            durationSeconds: $this->computeDuration($dto),
        );
    }

    private function computeDuration(TenantJobRecordRepositoryData $dto): ?int
    {
        if ($dto->startedAt === null || $dto->finishedAt === null) {
            return null;
        }

        return (int) $dto->startedAt->diffInSeconds($dto->finishedAt);
    }
}
```

- [ ] **Step 6: Run Pint**

```
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: Run the tests to confirm they pass**

```
php artisan test --compact --filter=TenantJobsAdminServiceTest
```

Expected: all 5 tests PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Admin/Data/AdminJobData.php \
        app/Admin/Services/TenantJobsAdminService.php \
        app/Admin/Tests/TenantJobsAdminServiceTest.php \
        app/Repositories/Central/TenantJobRecordRepository.php
git commit -m "feat(admin-jobs): add AdminJobData DTO, TenantJobsAdminService, and listAll repository method"
```

---

### Task 2: Web Controllers + Routes

**Files:**
- Create: `app/Admin/Http/Controllers/TenantJobsController.php`
- Modify: `app/Admin/Routes/web_admin.php` — add two routes

**Interfaces:**
- Consumes: `TenantJobsAdminService::listAll()`, `TenantJobsAdminService::listForTenant()`
- Produces:
  - `GET /admin/jobs` → `admin.jobs` → renders `Admin/Jobs/Index`
  - `GET /admin/tenants/{tenant}/jobs` → `admin.tenants.jobs` → renders `Admin/Tenants/Jobs`

- [ ] **Step 1: Write failing web tests**

Create `app/Admin/Tests/TenantJobsWebTest.php`:

```php
<?php

namespace App\Admin\Tests;

use App\Models\Central\Tenant;
use App\Models\Central\TenantJobRecord;
use App\Models\Central\User;
use App\TenantJobs\Enums\TenantJobStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantJobsWebTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeJob(Tenant $tenant, array $overrides = []): TenantJobRecord
    {
        return TenantJobRecord::create(array_merge([
            'id' => (string) Str::uuid(),
            'tracking_id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'job_class' => 'App\\Jobs\\SomeJob',
            'display_name' => 'Some Job',
            'status' => TenantJobStatus::Pending,
        ], $overrides));
    }

    // ── Global page ───────────────────────────────────────────────────────────

    public function test_global_jobs_page_requires_auth(): void
    {
        $this->get(route('admin.jobs'))->assertRedirect(route('login'));
    }

    public function test_global_jobs_page_renders(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $this->makeJob($tenant);

        $this->actingAs($user)
            ->get(route('admin.jobs'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Jobs/Index')
                ->has('jobs.data', 1)
                ->has('tenants')
            );
    }

    public function test_global_jobs_page_filters_by_status(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $this->makeJob($tenant, ['status' => TenantJobStatus::Failed]);
        $this->makeJob($tenant, ['status' => TenantJobStatus::Completed]);

        $this->actingAs($user)
            ->get(route('admin.jobs', ['status' => 'failed']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('jobs.data', 1));
    }

    public function test_global_jobs_page_filters_by_tenant(): void
    {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        $this->makeJob($tenant1);
        $this->makeJob($tenant2);

        $this->actingAs($user)
            ->get(route('admin.jobs', ['tenant_id' => $tenant1->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('jobs.data', 1));
    }

    // ── Per-tenant page ───────────────────────────────────────────────────────

    public function test_tenant_jobs_page_requires_auth(): void
    {
        $tenant = Tenant::factory()->create();

        $this->get(route('admin.tenants.jobs', $tenant))->assertRedirect(route('login'));
    }

    public function test_tenant_jobs_page_renders(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $this->makeJob($tenant);

        $this->actingAs($user)
            ->get(route('admin.tenants.jobs', $tenant))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Tenants/Jobs')
                ->where('tenant.id', $tenant->id)
                ->has('jobs.data', 1)
            );
    }

    public function test_tenant_jobs_page_scopes_to_tenant(): void
    {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        $this->makeJob($tenant1);
        $this->makeJob($tenant2);

        $this->actingAs($user)
            ->get(route('admin.tenants.jobs', $tenant1))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('jobs.data', 1));
    }

    public function test_tenant_jobs_page_filters_by_status(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $this->makeJob($tenant, ['status' => TenantJobStatus::Failed]);
        $this->makeJob($tenant, ['status' => TenantJobStatus::Completed]);

        $this->actingAs($user)
            ->get(route('admin.tenants.jobs', ['tenant' => $tenant->id, 'status' => 'failed']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('jobs.data', 1));
    }
}
```

- [ ] **Step 2: Run to confirm failure**

```
php artisan test --compact --filter=TenantJobsWebTest
```

Expected: FAIL — route `admin.jobs` not found.

- [ ] **Step 3: Create the web controller**

Create `app/Admin/Http/Controllers/TenantJobsController.php`:

```php
<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Services\TenantJobsAdminService;
use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\TenantJobs\Enums\TenantJobStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantJobsController extends Controller
{
    public function __construct(
        private readonly TenantJobsAdminService $service,
    ) {}

    public function index(Request $request): Response
    {
        $tenantId = $request->query('tenant_id') ? (int) $request->query('tenant_id') : null;
        $status = $request->query('status');
        $perPage = (int) ($request->query('per_page', 20));
        $statusEnum = $status ? TenantJobStatus::tryFrom($status) : null;

        return Inertia::render('Admin/Jobs/Index', [
            'jobs' => $this->service->listAll($perPage, $tenantId, $statusEnum),
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name']),
            'filters' => ['tenant_id' => $tenantId, 'status' => $status ?: null],
        ]);
    }

    public function indexForTenant(Request $request, Tenant $tenant): Response
    {
        $status = $request->query('status');
        $perPage = (int) ($request->query('per_page', 20));
        $statusEnum = $status ? TenantJobStatus::tryFrom($status) : null;

        return Inertia::render('Admin/Tenants/Jobs', [
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug],
            'jobs' => $this->service->listForTenant($tenant->id, $statusEnum, $perPage),
            'filters' => ['status' => $status ?: null],
        ]);
    }
}
```

- [ ] **Step 4: Register routes in `app/Admin/Routes/web_admin.php`**

Add these two lines inside the `auth` middleware group, after the errors routes:

```php
use App\Admin\Http\Controllers\TenantJobsController;
```

Add to the top `use` block (alphabetically near other uses), then add the routes after the errors block:

```php
    Route::get('/admin/jobs', [TenantJobsController::class, 'index'])->name('admin.jobs');
    Route::get('/admin/tenants/{tenant}/jobs', [TenantJobsController::class, 'indexForTenant'])->name('admin.tenants.jobs');
```

- [ ] **Step 5: Run Pint**

```
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Run the tests**

```
php artisan test --compact --filter=TenantJobsWebTest
```

Expected: all 7 tests PASS (Inertia page assertion will fail at component name until Vue page is created — if so, stub empty Vue files to unblock: see Task 4).

> **Note:** If tests fail because Inertia cannot find the component, create empty stub files:
> - `resources/js/Pages/Admin/Jobs/Index.vue` with `<template><div /></template>`
> - `resources/js/Pages/Admin/Tenants/Jobs.vue` with `<template><div /></template>`
> Then re-run. The stubs are replaced in Task 4.

- [ ] **Step 7: Commit**

```bash
git add app/Admin/Http/Controllers/TenantJobsController.php \
        app/Admin/Routes/web_admin.php \
        app/Admin/Tests/TenantJobsWebTest.php
git commit -m "feat(admin-jobs): add web controllers and routes for global and per-tenant job pages"
```

---

### Task 3: API Controllers + Routes

**Files:**
- Create: `app/Admin/Http/Controllers/TenantJobsApiController.php`
- Modify: `app/Admin/Routes/api_admin.php` — add two routes

**Interfaces:**
- Consumes: `TenantJobsAdminService::listAll()`, `TenantJobsAdminService::listForTenant()`
- Produces:
  - `GET /api/admin/jobs` → `admin.api.jobs` → JSON `{ data: {...paginated AdminJobData...} }`
  - `GET /api/admin/tenants/{tenant}/jobs` → `admin.api.tenants.jobs` → JSON `{ data: {...paginated TenantJobData...} }`

- [ ] **Step 1: Write failing API tests**

Create `app/Admin/Tests/TenantJobsApiTest.php`:

```php
<?php

namespace App\Admin\Tests;

use App\Models\Central\Tenant;
use App\Models\Central\TenantJobRecord;
use App\Models\Central\User;
use App\TenantJobs\Enums\TenantJobStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantJobsApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeJob(Tenant $tenant, array $overrides = []): TenantJobRecord
    {
        return TenantJobRecord::create(array_merge([
            'id' => (string) Str::uuid(),
            'tracking_id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'job_class' => 'App\\Jobs\\SomeJob',
            'display_name' => 'Some Job',
            'status' => TenantJobStatus::Pending,
        ], $overrides));
    }

    // ── Global endpoint ───────────────────────────────────────────────────────

    public function test_global_jobs_endpoint_requires_auth(): void
    {
        $this->getJson(route('admin.api.jobs'))->assertUnauthorized();
    }

    public function test_global_jobs_endpoint_returns_paginated_jobs(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['name' => 'Acme']);
        $this->makeJob($tenant);

        $this->actingAs($user, 'sanctum')
            ->getJson(route('admin.api.jobs'))
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.tenant_name', 'Acme');
    }

    public function test_global_jobs_endpoint_filters_by_status(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $this->makeJob($tenant, ['status' => TenantJobStatus::Failed]);
        $this->makeJob($tenant, ['status' => TenantJobStatus::Completed]);

        $this->actingAs($user, 'sanctum')
            ->getJson(route('admin.api.jobs', ['status' => 'failed']))
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_global_jobs_endpoint_filters_by_tenant(): void
    {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        $this->makeJob($tenant1);
        $this->makeJob($tenant2);

        $this->actingAs($user, 'sanctum')
            ->getJson(route('admin.api.jobs', ['tenant_id' => $tenant1->id]))
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    // ── Per-tenant endpoint ───────────────────────────────────────────────────

    public function test_tenant_jobs_endpoint_requires_auth(): void
    {
        $tenant = Tenant::factory()->create();

        $this->getJson(route('admin.api.tenants.jobs', $tenant))->assertUnauthorized();
    }

    public function test_tenant_jobs_endpoint_returns_paginated_jobs(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $this->makeJob($tenant);

        $this->actingAs($user, 'sanctum')
            ->getJson(route('admin.api.tenants.jobs', $tenant))
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_tenant_jobs_endpoint_scopes_to_tenant(): void
    {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        $this->makeJob($tenant1);
        $this->makeJob($tenant2);

        $this->actingAs($user, 'sanctum')
            ->getJson(route('admin.api.tenants.jobs', $tenant1))
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_tenant_jobs_endpoint_filters_by_status(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $this->makeJob($tenant, ['status' => TenantJobStatus::Failed]);
        $this->makeJob($tenant, ['status' => TenantJobStatus::Completed]);

        $this->actingAs($user, 'sanctum')
            ->getJson(route('admin.api.tenants.jobs', ['tenant' => $tenant->id, 'status' => 'failed']))
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_response_includes_status_field(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $this->makeJob($tenant, ['status' => TenantJobStatus::Failed]);

        $this->actingAs($user, 'sanctum')
            ->getJson(route('admin.api.tenants.jobs', $tenant))
            ->assertJsonPath('data.data.0.status', 'failed');
    }
}
```

- [ ] **Step 2: Run to confirm failure**

```
php artisan test --compact --filter=TenantJobsApiTest
```

Expected: FAIL — route `admin.api.jobs` not found.

- [ ] **Step 3: Create the API controller**

Create `app/Admin/Http/Controllers/TenantJobsApiController.php`:

```php
<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Services\TenantJobsAdminService;
use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\TenantJobs\Enums\TenantJobStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantJobsApiController extends Controller
{
    public function __construct(
        private readonly TenantJobsAdminService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant_id') ? (int) $request->query('tenant_id') : null;
        $status = $request->query('status');
        $perPage = (int) ($request->query('per_page', 20));
        $statusEnum = $status ? TenantJobStatus::tryFrom($status) : null;

        return response()->json([
            'data' => $this->service->listAll($perPage, $tenantId, $statusEnum),
        ]);
    }

    public function indexForTenant(Request $request, Tenant $tenant): JsonResponse
    {
        $status = $request->query('status');
        $perPage = (int) ($request->query('per_page', 20));
        $statusEnum = $status ? TenantJobStatus::tryFrom($status) : null;

        return response()->json([
            'data' => $this->service->listForTenant($tenant->id, $statusEnum, $perPage),
        ]);
    }
}
```

- [ ] **Step 4: Register routes in `app/Admin/Routes/api_admin.php`**

Add `use App\Admin\Http\Controllers\TenantJobsApiController;` to the top `use` block, then add these two routes inside the existing `auth:sanctum` group, after the errors block:

```php
    Route::get('jobs', [TenantJobsApiController::class, 'index'])->name('api.jobs');
    Route::get('tenants/{tenant}/jobs', [TenantJobsApiController::class, 'indexForTenant'])->name('api.tenants.jobs');
```

These will resolve as `admin.api.jobs` and `admin.api.tenants.jobs` because the group already has `->name('admin.')`.

- [ ] **Step 5: Run Pint**

```
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Run the tests**

```
php artisan test --compact --filter=TenantJobsApiTest
```

Expected: all 8 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Admin/Http/Controllers/TenantJobsApiController.php \
        app/Admin/Routes/api_admin.php \
        app/Admin/Tests/TenantJobsApiTest.php
git commit -m "feat(admin-jobs): add API controllers and routes for global and per-tenant job endpoints"
```

---

### Task 4: Vue Pages + Layout Update

**Files:**
- Create: `resources/js/Pages/Admin/Jobs/Index.vue`
- Create: `resources/js/Pages/Admin/Tenants/Jobs.vue`
- Modify: `resources/js/Layouts/AdminTenantLayout.vue` — add Jobs to `tenantNav`

**Interfaces:**
- `Admin/Jobs/Index.vue` props: `{ jobs: Object, tenants: Array<{id, name}>, filters: {tenant_id, status} }`
- `Admin/Tenants/Jobs.vue` props: `{ tenant: {id, name, slug}, jobs: Object, filters: {status} }`

- [ ] **Step 1: Add Jobs to AdminTenantLayout nav**

In `resources/js/Layouts/AdminTenantLayout.vue`, find the `tenantNav` computed and update it to add Jobs after Errors:

```js
const tenantNav = computed(() => {
    const id = tenant.value?.id;
    if (!id) { return []; }
    return [
        { label: 'Settings', href: `/admin/tenants/${id}/settings` },
        { label: 'Users',    href: `/admin/tenants/${id}/users`    },
        { label: 'Reports',  href: `/admin/tenants/${id}/reports`  },
        { label: 'Errors',   href: `/admin/tenants/${id}/errors`   },
        { label: 'Jobs',     href: `/admin/tenants/${id}/jobs`     },
    ];
});
```

- [ ] **Step 2: Create `resources/js/Pages/Admin/Tenants/Jobs.vue`**

```vue
<script setup>
import { Head, router, usePoll } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminTenantLayout from '../../../Layouts/AdminTenantLayout.vue';

defineOptions({ layout: AdminTenantLayout });

const props = defineProps({
    tenant: Object,
    jobs: Object,
    filters: Object,
});

const statusConfig = {
    pending:   { label: 'Pending',   cls: 'bg-amber-500/20 text-amber-300' },
    running:   { label: 'Running',   cls: 'bg-blue-500/20 text-blue-300 animate-pulse' },
    completed: { label: 'Done',      cls: 'bg-emerald-500/20 text-emerald-400' },
    failed:    { label: 'Failed',    cls: 'bg-red-500/20 text-red-400' },
};

function statusBadge(status) {
    return statusConfig[status] ?? { label: status, cls: 'bg-slate-700 text-slate-400' };
}

const hasActiveJobs = computed(() =>
    props.jobs.data.some(j => j.status === 'pending' || j.status === 'running')
);

usePoll(4000, { only: ['jobs'] }, { autoStart: true });

const filterTabs = [
    { label: 'All',     value: null        },
    { label: 'Pending', value: 'pending'   },
    { label: 'Running', value: 'running'   },
    { label: 'Done',    value: 'completed' },
    { label: 'Failed',  value: 'failed'    },
];

function filterByStatus(status) {
    router.get(
        `/admin/tenants/${props.tenant.id}/jobs`,
        status ? { status } : {},
        { preserveState: true, replace: true },
    );
}

function formatDate(val) {
    if (!val) { return '—'; }
    return new Date(val).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatDuration(seconds) {
    if (seconds === null || seconds === undefined) { return '—'; }
    if (seconds < 60) { return `${seconds}s`; }
    return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
}

function goToPage(url) {
    if (url) { router.visit(url, { preserveScroll: true }); }
}
</script>

<template>
    <Head :title="`${tenant.name} — Job Queue`" />

    <main class="p-8 max-w-6xl">
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-slate-500 mb-6">
            <a href="/admin/tenants" class="hover:text-slate-300 transition">Tenants</a>
            <span>/</span>
            <span class="text-slate-300">{{ tenant.name }}</span>
            <span>/</span>
            <span class="text-slate-300">Job Queue</span>
        </div>

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-semibold text-white">{{ tenant.name }} — Job Queue</h1>
                <p class="text-sm text-slate-500 mt-0.5">Background tasks for this tenant · auto-refreshes every 4s while active.</p>
            </div>
            <span
                v-if="hasActiveJobs"
                class="inline-flex items-center gap-1.5 text-xs text-blue-300 bg-blue-500/10 border border-blue-500/20 px-3 py-1.5 rounded-full"
            >
                <span class="w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse" />
                Live
            </span>
        </div>

        <!-- Status filter tabs -->
        <div class="flex gap-1 mb-6">
            <button
                v-for="tab in filterTabs"
                :key="tab.label"
                :class="[
                    'text-xs px-3 py-1.5 rounded-lg font-medium transition',
                    filters.status === tab.value
                        ? 'bg-blue-600 text-white'
                        : 'text-slate-400 hover:text-white hover:bg-white/5',
                ]"
                @click="filterByStatus(tab.value)"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Table -->
        <div class="bg-slate-900 border border-white/5 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-white/5 text-xs text-slate-500 uppercase tracking-wide">
                        <th class="text-left px-4 py-3 font-medium">Job</th>
                        <th class="text-left px-4 py-3 font-medium">Status</th>
                        <th class="text-left px-4 py-3 font-medium">Queued</th>
                        <th class="text-left px-4 py-3 font-medium">Duration</th>
                        <th class="text-left px-4 py-3 font-medium">Error</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="jobs.data.length === 0">
                        <td colspan="5" class="px-4 py-12 text-center text-slate-600">No jobs found.</td>
                    </tr>
                    <tr
                        v-for="job in jobs.data"
                        :key="job.id"
                        class="border-b border-white/5 last:border-0 hover:bg-white/[0.02] transition"
                    >
                        <td class="px-4 py-3">
                            <p class="text-slate-200 text-xs font-medium">{{ job.display_name }}</p>
                            <p class="text-slate-600 text-xs font-mono mt-0.5">{{ job.job_class }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', statusBadge(job.status).cls]">
                                {{ statusBadge(job.status).label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-500 text-xs">{{ formatDate(job.created_at) }}</td>
                        <td class="px-4 py-3 text-slate-500 text-xs">{{ formatDuration(job.duration_seconds) }}</td>
                        <td class="px-4 py-3 text-red-400 text-xs max-w-xs truncate" :title="job.error_message ?? ''">
                            {{ job.error_message ?? '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="jobs.last_page > 1" class="flex items-center justify-between mt-4 text-sm text-slate-500">
            <span>Page {{ jobs.current_page }} of {{ jobs.last_page }}</span>
            <div class="flex gap-2">
                <button
                    @click="goToPage(jobs.prev_page_url)"
                    :disabled="!jobs.prev_page_url"
                    class="px-3 py-1.5 rounded-lg border border-white/10 hover:bg-white/5 disabled:opacity-30 disabled:cursor-not-allowed transition text-slate-300"
                >
                    Previous
                </button>
                <button
                    @click="goToPage(jobs.next_page_url)"
                    :disabled="!jobs.next_page_url"
                    class="px-3 py-1.5 rounded-lg border border-white/10 hover:bg-white/5 disabled:opacity-30 disabled:cursor-not-allowed transition text-slate-300"
                >
                    Next
                </button>
            </div>
        </div>
    </main>
</template>
```

- [ ] **Step 3: Create `resources/js/Pages/Admin/Jobs/` directory and `Index.vue`**

```bash
mkdir -p resources/js/Pages/Admin/Jobs
```

Create `resources/js/Pages/Admin/Jobs/Index.vue`:

```vue
<script setup>
import { Head, Link, router, usePage, usePoll } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

function logout() {
    router.post('/logout');
}

const props = defineProps({
    jobs: Object,
    tenants: Array,
    filters: Object,
});

const statusConfig = {
    pending:   { label: 'Pending',   cls: 'bg-amber-500/20 text-amber-300' },
    running:   { label: 'Running',   cls: 'bg-blue-500/20 text-blue-300 animate-pulse' },
    completed: { label: 'Done',      cls: 'bg-emerald-500/20 text-emerald-400' },
    failed:    { label: 'Failed',    cls: 'bg-red-500/20 text-red-400' },
};

function statusBadge(status) {
    return statusConfig[status] ?? { label: status, cls: 'bg-slate-700 text-slate-400' };
}

const hasActiveJobs = computed(() =>
    props.jobs.data.some(j => j.status === 'pending' || j.status === 'running')
);

usePoll(4000, { only: ['jobs'] }, { autoStart: true });

const filterTabs = [
    { label: 'All',     value: null        },
    { label: 'Pending', value: 'pending'   },
    { label: 'Running', value: 'running'   },
    { label: 'Done',    value: 'completed' },
    { label: 'Failed',  value: 'failed'    },
];

function applyFilters(patch) {
    const current = {
        tenant_id: props.filters.tenant_id ?? null,
        status: props.filters.status ?? null,
    };
    const merged = { ...current, ...patch };
    const query = Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== null && v !== '')
    );
    router.get('/admin/jobs', query, { preserveState: true, replace: true });
}

function formatDate(val) {
    if (!val) { return '—'; }
    return new Date(val).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatDuration(seconds) {
    if (seconds === null || seconds === undefined) { return '—'; }
    if (seconds < 60) { return `${seconds}s`; }
    return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
}

function goToPage(url) {
    if (url) { router.visit(url, { preserveScroll: true }); }
}
</script>

<template>
    <Head title="Job Queue" />

    <div class="min-h-screen bg-slate-950 text-slate-100 flex">
        <!-- Sidebar -->
        <aside class="fixed inset-y-0 left-0 w-60 bg-slate-900 border-r border-white/5 flex flex-col">
            <div class="h-16 flex items-center px-6 border-b border-white/5 shrink-0">
                <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center mr-3 shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <span class="font-semibold text-white">SSO Admin</span>
            </div>
            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                <Link href="/admin" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">Dashboard</Link>
                <Link href="/admin/users" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">Users</Link>
                <Link href="/admin/apps" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">Apps</Link>
                <Link href="/admin/tenants" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">Tenants</Link>
                <Link href="/admin/jobs" class="flex items-center gap-3 py-2 text-sm font-medium transition border-l-2 border-blue-500 rounded-r-lg pl-[10px] pr-3 text-white">Jobs</Link>
                <Link href="/admin/settings" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-white/5">Settings</Link>
            </nav>
            <div class="p-3 border-t border-white/5 shrink-0 space-y-1">
                <Link href="/apps" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-slate-400 hover:text-white hover:bg-white/5 transition">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    App Selection
                </Link>
                <button @click="logout" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-slate-400 hover:text-white hover:bg-white/5 transition">
                    Sign out
                </button>
            </div>
        </aside>

        <!-- Content -->
        <div class="ml-60 flex-1 p-8">
            <div class="max-w-6xl">
                <!-- Header -->
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-xl font-semibold text-white">Job Queue</h1>
                        <p class="text-sm text-slate-500 mt-0.5">Background tasks across all tenants · auto-refreshes every 4s while active.</p>
                    </div>
                    <span
                        v-if="hasActiveJobs"
                        class="inline-flex items-center gap-1.5 text-xs text-blue-300 bg-blue-500/10 border border-blue-500/20 px-3 py-1.5 rounded-full"
                    >
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse" />
                        Live
                    </span>
                </div>

                <!-- Filters row -->
                <div class="flex items-center gap-3 mb-6">
                    <!-- Tenant dropdown -->
                    <select
                        :value="filters.tenant_id ?? ''"
                        @change="applyFilters({ tenant_id: $event.target.value || null })"
                        class="text-xs bg-slate-900 border border-white/10 rounded-lg px-3 py-1.5 text-slate-300 focus:outline-none focus:border-blue-500"
                    >
                        <option value="">All tenants</option>
                        <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>

                    <!-- Status tabs -->
                    <div class="flex gap-1">
                        <button
                            v-for="tab in filterTabs"
                            :key="tab.label"
                            :class="[
                                'text-xs px-3 py-1.5 rounded-lg font-medium transition',
                                filters.status === tab.value
                                    ? 'bg-blue-600 text-white'
                                    : 'text-slate-400 hover:text-white hover:bg-white/5',
                            ]"
                            @click="applyFilters({ status: tab.value })"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-slate-900 border border-white/5 rounded-xl overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-white/5 text-xs text-slate-500 uppercase tracking-wide">
                                <th class="text-left px-4 py-3 font-medium">Tenant</th>
                                <th class="text-left px-4 py-3 font-medium">Job</th>
                                <th class="text-left px-4 py-3 font-medium">Status</th>
                                <th class="text-left px-4 py-3 font-medium">Queued</th>
                                <th class="text-left px-4 py-3 font-medium">Duration</th>
                                <th class="text-left px-4 py-3 font-medium">Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="jobs.data.length === 0">
                                <td colspan="6" class="px-4 py-12 text-center text-slate-600">No jobs found.</td>
                            </tr>
                            <tr
                                v-for="job in jobs.data"
                                :key="job.id"
                                class="border-b border-white/5 last:border-0 hover:bg-white/[0.02] transition"
                            >
                                <td class="px-4 py-3 text-slate-400 text-xs">{{ job.tenant_name }}</td>
                                <td class="px-4 py-3">
                                    <p class="text-slate-200 text-xs font-medium">{{ job.display_name }}</p>
                                    <p class="text-slate-600 text-xs font-mono mt-0.5">{{ job.job_class }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', statusBadge(job.status).cls]">
                                        {{ statusBadge(job.status).label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-500 text-xs">{{ formatDate(job.created_at) }}</td>
                                <td class="px-4 py-3 text-slate-500 text-xs">{{ formatDuration(job.duration_seconds) }}</td>
                                <td class="px-4 py-3 text-red-400 text-xs max-w-xs truncate" :title="job.error_message ?? ''">
                                    {{ job.error_message ?? '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="jobs.last_page > 1" class="flex items-center justify-between mt-4 text-sm text-slate-500">
                    <span>Page {{ jobs.current_page }} of {{ jobs.last_page }}</span>
                    <div class="flex gap-2">
                        <button
                            @click="goToPage(jobs.prev_page_url)"
                            :disabled="!jobs.prev_page_url"
                            class="px-3 py-1.5 rounded-lg border border-white/10 hover:bg-white/5 disabled:opacity-30 disabled:cursor-not-allowed transition text-slate-300"
                        >
                            Previous
                        </button>
                        <button
                            @click="goToPage(jobs.next_page_url)"
                            :disabled="!jobs.next_page_url"
                            class="px-3 py-1.5 rounded-lg border border-white/10 hover:bg-white/5 disabled:opacity-30 disabled:cursor-not-allowed transition text-slate-300"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
```

- [ ] **Step 4: Run all three test suites to confirm everything passes**

```
php artisan test --compact --filter=TenantJobsWebTest
php artisan test --compact --filter=TenantJobsApiTest
php artisan test --compact --filter=TenantJobsAdminServiceTest
```

Expected: all tests PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Layouts/AdminTenantLayout.vue \
        resources/js/Pages/Admin/Jobs/Index.vue \
        resources/js/Pages/Admin/Tenants/Jobs.vue
git commit -m "feat(admin-jobs): add global and per-tenant job queue Vue pages, add Jobs to tenant sub-nav"
```

---

### Task 5: Full Test Suite Verification

- [ ] **Step 1: Run the full test suite**

```
php artisan test --compact
```

Expected: all tests PASS with no regressions.

- [ ] **Step 2: Build frontend assets**

```
npm run build
```

Expected: build succeeds with no errors.

- [ ] **Step 3: Commit if any fixes were needed**

If any tests failed and required fixes, commit those fixes:

```bash
git add -p
git commit -m "fix(admin-jobs): resolve test suite regressions"
```
