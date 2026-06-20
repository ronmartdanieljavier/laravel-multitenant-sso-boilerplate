<?php

namespace App\Reports\Policies;

use App\Models\Central\Report;
use App\Models\Central\User;

class ReportPolicy
{
    public function view(User $user, Report $report): bool
    {
        return $report->user_id === $user->id;
    }

    public function download(User $user, Report $report): bool
    {
        return $report->user_id === $user->id;
    }

    public function delete(User $user, Report $report): bool
    {
        return $report->user_id === $user->id;
    }
}
