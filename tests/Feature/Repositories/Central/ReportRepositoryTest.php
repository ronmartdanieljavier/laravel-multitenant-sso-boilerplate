<?php

namespace Tests\Feature\Repositories\Central;

use App\Data\Repositories\Central\ReportRepositoryData;
use App\Models\Central\Report;
use App\Models\Central\User;
use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportStatus;
use App\Repositories\Central\ReportRepository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class ReportRepositoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ReportRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(ReportRepository::class);
    }

    public function test_create_returns_report_repository_data(): void
    {
        $user = User::factory()->create();

        $dto = $this->repository->create([
            'user_id' => $user->id,
            'type' => 'user_activity',
            'format' => ReportFormat::Screen->value,
            'delivery' => ReportDelivery::Download->value,
            'status' => ReportStatus::Pending->value,
        ]);

        $this->assertInstanceOf(ReportRepositoryData::class, $dto);
        $this->assertIsString($dto->id);
        $this->assertNotEmpty($dto->id);
        $this->assertSame($user->id, $dto->userId);
        $this->assertSame('user_activity', $dto->type);
        $this->assertSame(ReportStatus::Pending, $dto->status);
    }

    public function test_create_persists_to_database(): void
    {
        $user = User::factory()->create();

        $dto = $this->repository->create([
            'user_id' => $user->id,
            'type' => 'audit_log',
            'format' => ReportFormat::Pdf->value,
            'delivery' => ReportDelivery::Download->value,
            'status' => ReportStatus::Pending->value,
        ]);

        $this->assertDatabaseHas('reports', ['id' => $dto->id, 'type' => 'audit_log']);
    }

    public function test_list_for_user_returns_length_aware_paginator(): void
    {
        $user = User::factory()->create();
        Report::factory()->count(3)->create(['user_id' => $user->id]);

        $result = $this->repository->listForUser($user->id);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertSame(3, $result->total());
    }

    public function test_list_for_user_returns_paginator_of_dtos(): void
    {
        $user = User::factory()->create();
        Report::factory()->create(['user_id' => $user->id]);

        $result = $this->repository->listForUser($user->id);

        $this->assertInstanceOf(ReportRepositoryData::class, $result->items()[0]);
    }

    public function test_list_for_user_only_returns_reports_for_that_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Report::factory()->count(2)->create(['user_id' => $user->id]);
        Report::factory()->create(['user_id' => $other->id]);

        $result = $this->repository->listForUser($user->id);

        $this->assertSame(2, $result->total());
        foreach ($result->items() as $dto) {
            $this->assertSame($user->id, $dto->userId);
        }
    }

    public function test_list_for_user_respects_per_page(): void
    {
        $user = User::factory()->create();
        Report::factory()->count(5)->create(['user_id' => $user->id]);

        $result = $this->repository->listForUser($user->id, 2);

        $this->assertCount(2, $result->items());
        $this->assertSame(5, $result->total());
    }
}
