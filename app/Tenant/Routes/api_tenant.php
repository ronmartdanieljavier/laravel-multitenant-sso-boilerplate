<?php

use App\Http\Middleware\ResolveTenantDatabase;
use App\Reports\Http\Controllers\TenantReportQueueApiController;
use App\Tenant\Http\Controllers\TenantDashboardApiController;
use App\Tenant\Http\Controllers\TenantSwitcherApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('tenant')
    ->name('api.tenant.')
    ->group(function (): void {
        Route::get('/tenants', [TenantSwitcherApiController::class, 'index'])->name('tenants.index');
        Route::post('/switch', [TenantSwitcherApiController::class, 'switch'])->name('switch');

        Route::middleware(ResolveTenantDatabase::class)->group(function (): void {
            Route::get('/dashboard', [TenantDashboardApiController::class, 'index'])->name('dashboard');
            Route::get('/reports', [TenantReportQueueApiController::class, 'index'])->name('reports.index');
            Route::post('/reports/quick', [TenantReportQueueApiController::class, 'quickDispatch'])->name('reports.quick');
            Route::post('/reports/{reportId}/retry', [TenantReportQueueApiController::class, 'retry'])->name('reports.retry');
        });
    });
