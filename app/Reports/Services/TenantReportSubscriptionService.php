<?php

namespace App\Reports\Services;

use App\Models\Tenant\ReportSubscription;
use App\Reports\Data\ReportSubscriptionData;
use Illuminate\Support\Collection;

class TenantReportSubscriptionService
{
    /**
     * @return Collection<int, ReportSubscriptionData>
     */
    public function list(): Collection
    {
        return ReportSubscription::on('tenant')
            ->latest()
            ->get()
            ->map(fn (ReportSubscription $s) => $this->toData($s));
    }

    public function create(array $validated): ReportSubscriptionData
    {
        $subscription = ReportSubscription::on('tenant')->create($validated);

        return $this->toData($subscription);
    }

    public function toggleActive(int $id): ReportSubscriptionData
    {
        $subscription = ReportSubscription::on('tenant')->findOrFail($id);
        $subscription->update(['is_active' => ! $subscription->is_active]);

        return $this->toData($subscription->fresh());
    }

    public function delete(int $id): void
    {
        ReportSubscription::on('tenant')->findOrFail($id)->delete();
    }

    private function toData(ReportSubscription $subscription): ReportSubscriptionData
    {
        return new ReportSubscriptionData(
            id: $subscription->id,
            type: $subscription->type,
            format: $subscription->format,
            frequency: $subscription->frequency,
            delivery: $subscription->delivery,
            recipients: $subscription->recipients,
            s3Path: $subscription->s3_path,
            isActive: $subscription->is_active,
            lastDispatchedAt: $subscription->last_dispatched_at,
            createdAt: $subscription->created_at,
        );
    }
}
