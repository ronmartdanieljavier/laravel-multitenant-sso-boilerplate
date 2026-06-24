<?php

use App\Admin\Http\Controllers\AppManagementApiController;
use App\Admin\Http\Controllers\SystemSettingsApiController;
use App\Admin\Http\Controllers\TenantManagementApiController;
use App\Admin\Http\Controllers\UserManagementApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('users', [UserManagementApiController::class, 'index'])->name('api.users');
    Route::post('users/invite', [UserManagementApiController::class, 'invite'])->name('api.users.invite');
    Route::put('users/{user}', [UserManagementApiController::class, 'update'])->name('api.users.update');

    Route::get('apps', [AppManagementApiController::class, 'index'])->name('api.apps');
    Route::put('apps/{app}', [AppManagementApiController::class, 'update'])->name('api.apps.update');

    Route::get('settings', [SystemSettingsApiController::class, 'index'])->name('api.settings');
    Route::put('settings', [SystemSettingsApiController::class, 'update'])->name('api.settings.update');

    Route::get('tenants', [TenantManagementApiController::class, 'index'])->name('api.tenants');
    Route::post('tenants/migrate-all', [TenantManagementApiController::class, 'migrateAll'])->name('api.tenants.migrateAll');
    Route::post('tenants', [TenantManagementApiController::class, 'store'])->name('api.tenants.store');
    Route::put('tenants/{tenant}', [TenantManagementApiController::class, 'update'])->name('api.tenants.update');
    Route::patch('tenants/{tenant}/active', [TenantManagementApiController::class, 'setActive'])->name('api.tenants.setActive');
    Route::post('tenants/{tenant}/migrate', [TenantManagementApiController::class, 'migrate'])->name('api.tenants.migrate');
    Route::delete('tenants/{tenant}', [TenantManagementApiController::class, 'destroy'])->name('api.tenants.destroy');
});
