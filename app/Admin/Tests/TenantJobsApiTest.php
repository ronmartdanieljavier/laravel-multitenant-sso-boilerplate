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
