<?php

namespace App\Providers;

use App\Models\Central\Report;
use App\Models\Central\User;
use App\Models\Tenant\ReportSubscription;
use App\Reports\Policies\ReportPolicy;
use App\Reports\Policies\ReportSubscriptionPolicy;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Report::class, ReportPolicy::class);
        Gate::policy(ReportSubscription::class, ReportSubscriptionPolicy::class);

        $this->callAfterResolving('migrator', function (Migrator $migrator) {
            $migrator->path(database_path('migrations/central'));
        });

        Horizon::auth(function ($request) {
            /** @var User|null $user */
            $user = $request->user();

            if ($user === null) {
                return false;
            }

            return Gate::allows('viewHorizon', $user);
        });
    }
}
