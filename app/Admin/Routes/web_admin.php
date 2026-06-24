<?php

use App\Admin\Http\Controllers\AppManagementController;
use App\Admin\Http\Controllers\SystemSettingsController;
use App\Admin\Http\Controllers\TenantHealthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {

    Route::get('/admin', function () {
        return Inertia::render('Admin/Index');
    })->name('admin');

    Route::get('/admin/apps', [AppManagementController::class, 'index'])->name('admin.apps');
    Route::put('/admin/apps/{app}', [AppManagementController::class, 'update'])->name('admin.apps.update');

    Route::get('/admin/tenants', [TenantHealthController::class, 'index'])->name('admin.tenants');

    Route::get('/admin/settings', [SystemSettingsController::class, 'index'])->name('admin.settings');
    Route::put('/admin/settings', [SystemSettingsController::class, 'update'])->name('admin.settings.update');
});
