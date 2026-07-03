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
        $user = $this->grantAdminRole(User::factory()->create());
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
        $user = $this->grantAdminRole(User::factory()->create());
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
        $user = $this->grantAdminRole(User::factory()->create());
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
        $user = $this->grantAdminRole(User::factory()->create());
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
        $user = $this->grantAdminRole(User::factory()->create());
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
        $user = $this->grantAdminRole(User::factory()->create());
        $tenant = Tenant::factory()->create();
        $this->makeJob($tenant, ['status' => TenantJobStatus::Failed]);
        $this->makeJob($tenant, ['status' => TenantJobStatus::Completed]);

        $this->actingAs($user)
            ->get(route('admin.tenants.jobs', ['tenant' => $tenant->id, 'status' => 'failed']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('jobs.data', 1));
    }
}
