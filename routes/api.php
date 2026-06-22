<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    foreach (glob(app_path('*/Routes/api_*.php')) as $routeFile) {
        require $routeFile;
    }
});
