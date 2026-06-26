<?php

namespace App\Repositories\Central;

use App\Data\Repositories\Central\TenantJobRecordRepositoryData;
use App\Models\Central\TenantJobRecord;
use App\TenantJobs\Enums\TenantJobStatus;
use Illuminate\Pagination\LengthAwarePaginator;

class TenantJobRecordRepository
{
    public function __construct(protected TenantJobRecord $model) {}

    public function create(array $data): TenantJobRecordRepositoryData
    {
        return TenantJobRecordRepositoryData::from($this->model->create($data));
    }

    public function markRunning(string $trackingId): void
    {
        $this->model->where('tracking_id', $trackingId)->update([
            'status' => TenantJobStatus::Running,
            'started_at' => now(),
        ]);
    }

    public function markCompleted(string $trackingId): void
    {
        $this->model->where('tracking_id', $trackingId)->update([
            'status' => TenantJobStatus::Completed,
            'finished_at' => now(),
        ]);
    }

    public function markFailed(string $trackingId, string $errorMessage): void
    {
        $this->model->where('tracking_id', $trackingId)->update([
            'status' => TenantJobStatus::Failed,
            'error_message' => $errorMessage,
            'finished_at' => now(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, TenantJobRecordRepositoryData>
     */
    public function listForTenant(
        int $tenantId,
        ?TenantJobStatus $status = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return $this->model->query()
            ->where('tenant_id', $tenantId)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->through(fn (TenantJobRecord $record) => TenantJobRecordRepositoryData::from($record));
    }
}
