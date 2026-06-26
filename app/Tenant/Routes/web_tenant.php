<?php

use App\Http\Middleware\ResolveWebTenantDatabase;
use App\Reports\Http\Controllers\ReportController;
use App\Reports\Http\Controllers\TenantReportQueueController;
use App\Reports\Http\Controllers\TenantReportSubscriptionController;
use App\Tenant\Http\Controllers\TenantDashboardController;
use App\Tenant\Http\Controllers\TenantSwitcherController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {

    Route::middleware(ResolveWebTenantDatabase::class)->group(function () {
        Route::get('/tenant', [TenantDashboardController::class, 'index'])->name('tenant');

        Route::get('/tenant/reports', [TenantReportQueueController::class, 'index'])->name('tenant.reports');
        Route::post('/tenant/reports/quick', [TenantReportQueueController::class, 'quickDispatch'])->name('tenant.reports.quick');
        Route::post('/tenant/reports/{reportId}/retry', [TenantReportQueueController::class, 'retry'])->name('tenant.reports.retry');
        Route::get('/tenant/reports/{report}/download', [ReportController::class, 'download'])->name('tenant.reports.download');

        Route::prefix('tenant/reports/subscriptions')->name('tenant.reports.subscriptions.')->group(function (): void {
            Route::post('/', [TenantReportSubscriptionController::class, 'store'])->name('store');
            Route::put('/{id}', [TenantReportSubscriptionController::class, 'toggle'])->name('toggle');
            Route::delete('/{id}', [TenantReportSubscriptionController::class, 'destroy'])->name('destroy');
        });

        Route::post('/tenant/switch', [TenantSwitcherController::class, 'switch'])->name('tenant.switch');
    });

});
