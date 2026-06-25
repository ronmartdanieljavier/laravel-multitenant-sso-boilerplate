<?php

use App\Http\Middleware\ResolveTenantDatabase;
use App\Reports\Http\Controllers\TenantReportQueueApiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', ResolveTenantDatabase::class])
    ->prefix('tenant')
    ->name('tenant.')
    ->group(function (): void {
        Route::get('/reports', [TenantReportQueueApiController::class, 'index'])->name('reports.index');
    });
