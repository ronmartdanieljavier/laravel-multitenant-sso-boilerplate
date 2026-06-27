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
