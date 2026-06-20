<?php

namespace App\Reports\Policies;

use App\Models\Central\User;
use App\Models\Tenant\ReportSubscription;

class ReportSubscriptionPolicy
{
    public function view(User $user, ReportSubscription $subscription): bool
    {
        return true;
    }

    public function update(User $user, ReportSubscription $subscription): bool
    {
        return true;
    }

    public function delete(User $user, ReportSubscription $subscription): bool
    {
        return true;
    }
}
