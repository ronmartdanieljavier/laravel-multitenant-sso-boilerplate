<?php

namespace App\TenantJobs\Tests;

use App\Models\Central\Tenant;
use App\Models\Central\TenantJobRecord;
use App\TenantJobs\Data\TenantJobData;
use App\TenantJobs\Enums\TenantJobStatus;
use App\TenantJobs\Services\TenantJobsPortalService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantJobsServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private TenantJobsPortalService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TenantJobsPortalService::class);
    }

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

    public function test_list_for_tenant_returns_tenant_job_data_instances(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'svc-jobs-type']);
        $this->jobRecord($tenant);

        $result = $this->service->listForTenant($tenant->id);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(TenantJobData::class, $result->first());
    }

    public function test_list_for_tenant_is_scoped_to_tenant(): void
    {
        $tenant1 = Tenant::factory()->create(['slug' => 'svc-scope1']);
        $tenant2 = Tenant::factory()->create(['slug' => 'svc-scope2']);
        $this->jobRecord($tenant1, ['display_name' => 'Job A']);
        $this->jobRecord($tenant2, ['display_name' => 'Job B']);

        $result = $this->service->listForTenant($tenant1->id);

        $this->assertCount(1, $result);
        $this->assertSame('Job A', $result->first()->displayName);
    }

    public function test_status_filter_returns_matching_jobs_only(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'svc-status']);
        $this->jobRecord($tenant, ['status' => TenantJobStatus::Pending]);
        $this->jobRecord($tenant, ['status' => TenantJobStatus::Failed]);

        $result = $this->service->listForTenant($tenant->id, 'failed');

        $this->assertCount(1, $result);
        $this->assertSame(TenantJobStatus::Failed, $result->first()->status);
    }

    public function test_duration_seconds_is_computed_from_started_and_finished_at(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'svc-duration']);
        $this->jobRecord($tenant, [
            'status' => TenantJobStatus::Completed,
            'started_at' => Carbon::parse('2026-01-01 10:00:00'),
            'finished_at' => Carbon::parse('2026-01-01 10:00:45'),
        ]);

        $result = $this->service->listForTenant($tenant->id);

        $this->assertSame(45, $result->first()->durationSeconds);
    }

    public function test_duration_seconds_is_null_when_not_started(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'svc-dur-null']);
        $this->jobRecord($tenant, ['status' => TenantJobStatus::Pending]);

        $result = $this->service->listForTenant($tenant->id);

        $this->assertNull($result->first()->durationSeconds);
    }
}
