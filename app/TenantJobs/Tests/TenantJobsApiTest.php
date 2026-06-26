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

class TenantJobsApiTest extends TestCase
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

    public function test_unauthenticated_request_is_rejected(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'api-jobs-unauth']);
        $this->setFakeTenant($tenant);

        $this->getJson('/api/tenant/jobs')->assertUnauthorized();
    }

    public function test_authenticated_user_can_list_jobs(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'api-jobs-index']);
        $this->jobRecord($tenant);
        $this->setFakeTenant($tenant);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/tenant/jobs')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_jobs_are_scoped_to_current_tenant(): void
    {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create(['slug' => 'api-jobs-scope1']);
        $tenant2 = Tenant::factory()->create(['slug' => 'api-jobs-scope2']);
        $this->jobRecord($tenant1, ['display_name' => 'Tenant 1 Job']);
        $this->jobRecord($tenant2, ['display_name' => 'Tenant 2 Job']);
        $this->setFakeTenant($tenant1);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/tenant/jobs')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    public function test_status_filter_scopes_results(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'api-jobs-filter']);
        $this->jobRecord($tenant, ['display_name' => 'Running Job', 'status' => TenantJobStatus::Running]);
        $this->jobRecord($tenant, ['display_name' => 'Completed Job', 'status' => TenantJobStatus::Completed]);
        $this->setFakeTenant($tenant);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/tenant/jobs?status=completed')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }
}
