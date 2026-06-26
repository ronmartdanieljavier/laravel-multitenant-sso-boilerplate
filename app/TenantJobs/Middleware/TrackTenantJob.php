<?php

namespace App\TenantJobs\Middleware;

use App\Repositories\Central\TenantJobRecordRepository;
use Closure;
use Throwable;

class TrackTenantJob
{
    public function handle(mixed $job, Closure $next): void
    {
        if (! isset($job->tenantJobTrackingId) || $job->tenantJobTrackingId === '') {
            $next($job);

            return;
        }

        $repo = app(TenantJobRecordRepository::class);
        $repo->markRunning($job->tenantJobTrackingId);

        try {
            $next($job);
            $repo->markCompleted($job->tenantJobTrackingId);
        } catch (Throwable $e) {
            // failed() on the job handles the failed status after all retries
            throw $e;
        }
    }
}
