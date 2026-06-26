<?php

use App\Http\Middleware\ResolveWebTenantDatabase;
use App\Reports\Http\Controllers\TenantReportQueueController;
use App\Tenant\Http\Controllers\TenantSwitcherController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {

    Route::middleware(ResolveWebTenantDatabase::class)->group(function () {
        Route::get('/tenant', function () {
            return Inertia::render('Tenant/Index');
        })->name('tenant');

        Route::get('/tenant/reports', [TenantReportQueueController::class, 'index'])->name('tenant.reports');

        Route::post('/tenant/switch', [TenantSwitcherController::class, 'switch'])->name('tenant.switch');
    });

});
