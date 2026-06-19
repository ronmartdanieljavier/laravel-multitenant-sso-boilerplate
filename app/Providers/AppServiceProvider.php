<?php

namespace App\Providers;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->callAfterResolving('migrator', function (Migrator $migrator) {
            $migrator->path(database_path('migrations/central'));
            $migrator->path(database_path('migrations/tenant'));
        });
    }
}
