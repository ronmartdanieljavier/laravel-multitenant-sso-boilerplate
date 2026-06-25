<?php

namespace Tests\Feature\Repositories\Central;

use App\Data\Repositories\Central\ReportRepositoryData;
use App\Models\Central\Report;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportStatus;
use App\Repositories\Central\ReportRepository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReportRepositoryTenantTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ReportRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(ReportRepository::class);
    }

    private function makeReport(Tenant $tenant, User $user, array $overrides = []): Report
    {
        return Report::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'type' => 'user_activity',
            'format' => ReportFormat::Screen,
            'delivery' => ReportDelivery::None,
            'status' => ReportStatus::Pending,
        ], $overrides));
    }

    public function test_list_for_tenant_returns_paginator_of_dtos(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $this->makeReport($tenant, $user);

        $result = $this->repository->listForTenant($tenant->id);

        $this->assertSame(1, $result->total());
        $this->assertInstanceOf(ReportRepositoryData::class, $result->items()[0]);
    }

    public function test_list_for_tenant_excludes_other_tenants(): void
    {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        $this->makeReport($tenant1, $user);
        $this->makeReport($tenant2, $user);

        $result = $this->repository->listForTenant($tenant1->id);

        $this->assertSame(1, $result->total());
    }

    public function test_list_for_tenant_orders_by_latest(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $old = $this->makeReport($tenant, $user, ['created_at' => now()->subHour()]);
        $new = $this->makeReport($tenant, $user, ['created_at' => now()]);

        $items = $this->repository->listForTenant($tenant->id)->items();

        $this->assertSame($new->id, $items[0]->id);
    }

    public function test_list_for_tenant_respects_per_page(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->makeReport($tenant, $user);
        }

        $result = $this->repository->listForTenant($tenant->id, perPage: 2);

        $this->assertCount(2, $result->items());
        $this->assertSame(5, $result->total());
    }

    public function test_list_for_tenant_returns_empty_for_unknown_tenant(): void
    {
        $result = $this->repository->listForTenant(99999);

        $this->assertSame(0, $result->total());
    }
}
