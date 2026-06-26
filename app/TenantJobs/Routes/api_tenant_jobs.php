<?php

use App\Http\Middleware\ResolveTenantDatabase;
use App\TenantJobs\Http\Controllers\TenantJobsApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('tenant')
    ->name('tenant.')
    ->group(function (): void {
        Route::middleware(ResolveTenantDatabase::class)->group(function (): void {
            Route::get('/jobs', [TenantJobsApiController::class, 'index'])->name('jobs.index');
        });
    });
