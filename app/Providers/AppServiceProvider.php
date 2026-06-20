<?php

namespace App\Providers;

use App\Models\Central\Report;
use App\Models\Central\User;
use App\Reports\Policies\ReportPolicy;
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

        Gate::define('viewHorizon', function (User $user) {
            // TODO: restrict to admin users once an is_admin flag or role is added to the users table.
            return app()->environment('local');
        });
    }
}
