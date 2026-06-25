<?php

namespace App\Admin\Data;

use Spatie\LaravelData\Data;

class DashboardStatsData extends Data
{
    public function __construct(
        public int $totalUsers,
        public int $activeUsers,
        public int $pendingInvitationUsers,
        public int $activeApps,
        public int $activeTenants,
        public int $activeSsoSessions,
    ) {}
}
