<?php

namespace App\TenantJobs\Concerns;

use App\Repositories\Central\TenantJobRecordRepository;
use App\TenantJobs\Enums\TenantJobStatus;
use App\TenantJobs\Middleware\TrackTenantJob;
use Illuminate\Support\Str;
use Throwable;

trait TenantJobTrackable
{
    public string $tenantJobTrackingId = '';

    public int $tenantId = 0;

    public ?int $userId = null;

    public string $jobDisplayName = '';

    public static function initTracking(
        self $job,
        int $tenantId,
        ?int $userId,
        string $displayName,
    ): void {
        $job->tenantJobTrackingId = (string) Str::uuid();
        $job->tenantId = $tenantId;
        $job->userId = $userId;
        $job->jobDisplayName = $displayName;

        app(TenantJobRecordRepository::class)->create([
            'tracking_id' => $job->tenantJobTrackingId,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'job_class' => static::class,
            'display_name' => $displayName,
            'status' => TenantJobStatus::Pending,
        ]);
    }

    public function trackingMiddleware(): array
    {
        return [new TrackTenantJob];
    }

    public function failed(Throwable $e): void
    {
        if ($this->tenantJobTrackingId === '') {
            return;
        }

        app(TenantJobRecordRepository::class)->markFailed($this->tenantJobTrackingId, $e->getMessage());
    }
}
