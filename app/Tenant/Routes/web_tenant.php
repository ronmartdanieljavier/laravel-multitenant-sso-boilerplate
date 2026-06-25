<?php

use App\Http\Middleware\ResolveTenantDatabase;
use App\Reports\Http\Controllers\TenantReportQueueController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {

    Route::get('/tenant', function () {
        return Inertia::render('Tenant/Index');
    })->name('tenant');

    Route::middleware(ResolveTenantDatabase::class)->group(function () {
        Route::get('/tenant/reports', [TenantReportQueueController::class, 'index'])->name('tenant.reports');
    });

});
