<?php

namespace App\Repositories\Central;

use App\Data\Repositories\Central\ReportRepositoryData;
use App\Models\Central\Report;
use App\Reports\Enums\ReportStatus;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     * Count pending, processing, and failed reports grouped by tenant.
     * Each row is a stdClass with: tenant_id, tenant_name, tenant_slug, status, count.
     *
     * @return Collection<int, \stdClass>
     */
    public function countQueueStatusByTenant(): Collection
    {
        return DB::table('reports')
            ->join('tenants', 'reports.tenant_id', '=', 'tenants.id')
            ->whereIn('reports.status', [
                ReportStatus::Pending->value,
                ReportStatus::Processing->value,
                ReportStatus::Failed->value,
            ])
            ->selectRaw('reports.tenant_id, tenants.name as tenant_name, tenants.slug as tenant_slug, reports.status, COUNT(*) as count')
            ->groupBy('reports.tenant_id', 'tenants.name', 'tenants.slug', 'reports.status')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ReportRepositoryData
    {
        return ReportRepositoryData::from($this->model->create($data));
    }

    /**
     * Return status counts + this-month success count for a single tenant.
     *
     * @return array{pending: int, processing: int, failed: int, successThisMonth: int}
     */
    public function summaryForTenant(int $tenantId): array
    {
        $counts = $this->model->query()
            ->where('tenant_id', $tenantId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->map(fn ($v) => (int) $v);

        $successThisMonth = $this->model->query()
            ->where('tenant_id', $tenantId)
            ->where('status', ReportStatus::Success)
            ->whereMonth('completed_at', now()->month)
            ->whereYear('completed_at', now()->year)
            ->count();

        return [
            'pending' => $counts->get(ReportStatus::Pending->value, 0),
            'processing' => $counts->get(ReportStatus::Processing->value, 0),
            'failed' => $counts->get(ReportStatus::Failed->value, 0),
            'successThisMonth' => $successThisMonth,
        ];
    }

    /**
     * @return Collection<int, ReportRepositoryData>
     */
    public function recentForTenant(int $tenantId, int $limit = 5): Collection
    {
        return $this->model->query()
            ->with('user:id,name,email')
            ->where('tenant_id', $tenantId)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Report $report) => ReportRepositoryData::from($report));
    }

    public function resetForRetry(string $id): ReportRepositoryData
    {
        $report = $this->model->findOrFail($id);

        $report->update([
            'status' => ReportStatus::Pending,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
        ]);

        return ReportRepositoryData::from($report->fresh());
    }
}
