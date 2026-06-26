<?php

use App\Http\Middleware\ResolveTenantDatabase;
use App\Reports\Http\Controllers\TenantReportQueueApiController;
use App\Tenant\Http\Controllers\TenantSwitcherApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('tenant')
    ->name('tenant.')
    ->group(function (): void {
        Route::get('/tenants', [TenantSwitcherApiController::class, 'index'])->name('tenants.index');
        Route::post('/switch', [TenantSwitcherApiController::class, 'switch'])->name('switch');

        Route::middleware(ResolveTenantDatabase::class)->group(function (): void {
            Route::get('/reports', [TenantReportQueueApiController::class, 'index'])->name('reports.index');
        });
    });
