<?php

use App\Http\Middleware\ResolveTenantDatabase;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', ResolveTenantDatabase::class])
    ->prefix('tenant')
    ->name('tenant.')
    ->group(function (): void {
        // Tenant routes go here
    });
