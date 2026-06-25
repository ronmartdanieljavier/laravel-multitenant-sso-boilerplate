<?php

namespace App\Repositories\Central;

use App\Data\Repositories\Central\ReportRepositoryData;
use App\Models\Central\Report;
use App\Reports\Enums\ReportStatus;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ReportRepository
{
    public function __construct(
        protected Report $model
    ) {}

    /**
     * Get a map of tenant_id => pending report count.
     *
     * @return Collection<int|string, int>
     */
    public function pendingCountByTenant(): Collection
    {
        return $this->model->query()
            ->where('status', ReportStatus::Pending)
            ->selectRaw('tenant_id, COUNT(*) as count')
            ->groupBy('tenant_id')
            ->pluck('count', 'tenant_id');
    }

    /**
     * Get a map of tenant_id => failed report count.
     *
     * @return Collection<int|string, int>
     */
    public function failedCountByTenant(): Collection
    {
        return $this->model->query()
            ->where('status', ReportStatus::Failed)
            ->selectRaw('tenant_id, COUNT(*) as count')
            ->groupBy('tenant_id')
            ->pluck('count', 'tenant_id');
    }

    /**
     * Get a map of tenant_id => last successful report completed_at timestamp.
     *
     * @return Collection<int|string, string>
     */
    public function lastSuccessAtByTenant(): Collection
    {
        return $this->model->query()
            ->where('status', ReportStatus::Success)
            ->selectRaw('tenant_id, MAX(completed_at) as last_report_at')
            ->groupBy('tenant_id')
            ->pluck('last_report_at', 'tenant_id');
    }

    /**
     * @return LengthAwarePaginator<int, ReportRepositoryData>
     */
    public function listForUser(int $userId, int $perPage = 20): LengthAwarePaginator
    {
        return $this->model->query()
            ->where('user_id', $userId)
            ->latest()
            ->paginate($perPage)
            ->through(fn (Report $report) => ReportRepositoryData::from($report));
    }

    /**
     * @return LengthAwarePaginator<int, ReportRepositoryData>
     */
    public function listForTenant(int $tenantId, int $perPage = 25): LengthAwarePaginator
    {
        return $this->model->query()
            ->with('user:id,name,email')
            ->where('tenant_id', $tenantId)
            ->latest()
            ->paginate($perPage)
            ->through(fn (Report $report) => ReportRepositoryData::from($report));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ReportRepositoryData
    {
        return ReportRepositoryData::from($this->model->create($data));
    }
}
