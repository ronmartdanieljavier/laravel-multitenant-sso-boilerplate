<?php

namespace App\Repositories\Central;

use App\Models\Central\Report;
use App\Reports\Enums\ReportStatus;
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
}
