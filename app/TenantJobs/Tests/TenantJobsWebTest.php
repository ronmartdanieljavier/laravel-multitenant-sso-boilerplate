<?php

namespace App\TenantJobs\Tests;

use App\Models\Central\Tenant;
use App\Models\Central\TenantJobRecord;
use App\Models\Central\User;
use App\TenantJobs\Enums\TenantJobStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\WithFakeTenantContext;
use Tests\TestCase;

class TenantJobsWebTest extends TestCase
{
    use LazilyRefreshDatabase, WithFakeTenantContext;

    private function jobRecord(Tenant $tenant, array $overrides = []): TenantJobRecord
    {
        return TenantJobRecord::create(array_merge([
            'id' => (string) Str::uuid(),
            'tracking_id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'job_class' => 'App\\Jobs\\SomeJob',
            'display_name' => 'Some Job',
            'status' => TenantJobStatus::Pending,
        ], $overrides));
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'jobs-unauth']);
        $this->setFakeTenant($tenant);

        $this->get(route('tenant.jobs'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_jobs_index(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'jobs-index']);
        $this->jobRecord($tenant);
        $this->setFakeTenant($tenant);

        $this->actingAs($user)
            ->get(route('tenant.jobs'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Jobs')
                ->has('jobs.data', 1)
            );
    }

    public function test_jobs_are_scoped_to_current_tenant(): void
    {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create(['slug' => 'jobs-scope1']);
        $tenant2 = Tenant::factory()->create(['slug' => 'jobs-scope2']);
        $this->jobRecord($tenant1, ['display_name' => 'Tenant 1 Job']);
        $this->jobRecord($tenant2, ['display_name' => 'Tenant 2 Job']);
        $this->setFakeTenant($tenant1);

        $this->actingAs($user)
            ->get(route('tenant.jobs'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('jobs.data', 1)
                ->where('jobs.data.0.display_name', 'Tenant 1 Job')
            );
    }

    public function test_status_filter_scopes_results(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'jobs-filter']);
        $this->jobRecord($tenant, ['display_name' => 'Pending Job', 'status' => TenantJobStatus::Pending]);
        $this->jobRecord($tenant, ['display_name' => 'Failed Job', 'status' => TenantJobStatus::Failed]);
        $this->setFakeTenant($tenant);

        $this->actingAs($user)
            ->get(route('tenant.jobs', ['status' => 'failed']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('jobs.data', 1)
                ->where('jobs.data.0.display_name', 'Failed Job')
            );
    }

    public function test_filters_prop_reflects_current_status_filter(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'jobs-filtercheck']);
        $this->setFakeTenant($tenant);

        $this->actingAs($user)
            ->get(route('tenant.jobs', ['status' => 'completed']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.status', 'completed')
            );
    }
}
