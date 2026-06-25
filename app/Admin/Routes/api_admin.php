<?php

use App\Admin\Http\Controllers\AppManagementApiController;
use App\Admin\Http\Controllers\DashboardApiController;
use App\Admin\Http\Controllers\SystemSettingsApiController;
use App\Admin\Http\Controllers\TenantErrorsApiController;
use App\Admin\Http\Controllers\TenantManagementApiController;
use App\Admin\Http\Controllers\TenantReportQueueApiController;
use App\Admin\Http\Controllers\TenantSettingsApiController;
use App\Admin\Http\Controllers\TenantUsersApiController;
use App\Admin\Http\Controllers\UserManagementApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('dashboard', [DashboardApiController::class, 'index'])->name('api.dashboard');

    Route::get('users', [UserManagementApiController::class, 'index'])->name('api.users');
    Route::post('users/invite', [UserManagementApiController::class, 'invite'])->name('api.users.invite');
    Route::put('users/{user}', [UserManagementApiController::class, 'update'])->name('api.users.update');
    Route::post('users/{user}/resend-invitation', [UserManagementApiController::class, 'resendInvitation'])->name('api.users.resendInvitation');

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

    Route::get('tenants/{tenant}/settings', [TenantSettingsApiController::class, 'index'])->name('api.tenants.settings');
    Route::put('tenants/{tenant}/settings', [TenantSettingsApiController::class, 'update'])->name('api.tenants.settings.update');
    Route::post('tenants/{tenant}/settings/logo', [TenantSettingsApiController::class, 'uploadLogo'])->name('api.tenants.settings.logo');
    Route::delete('tenants/{tenant}/settings/logo', [TenantSettingsApiController::class, 'deleteLogo'])->name('api.tenants.settings.logo.delete');

    Route::get('tenants/{tenant}/users', [TenantUsersApiController::class, 'index'])->name('api.tenants.users');
    Route::get('tenants/{tenant}/reports', [TenantReportQueueApiController::class, 'index'])->name('api.tenants.reports');

    Route::get('tenants/{tenant}/errors', [TenantErrorsApiController::class, 'index'])->name('api.tenants.errors');
    Route::get('tenants/{tenant}/errors/{error}', [TenantErrorsApiController::class, 'show'])->name('api.tenants.errors.show');
    Route::patch('tenants/{tenant}/errors/{error}/resolve', [TenantErrorsApiController::class, 'resolve'])->name('api.tenants.errors.resolve');
    Route::patch('tenants/{tenant}/errors/{error}/unresolve', [TenantErrorsApiController::class, 'unresolve'])->name('api.tenants.errors.unresolve');
    Route::delete('tenants/{tenant}/errors/{error}', [TenantErrorsApiController::class, 'destroy'])->name('api.tenants.errors.destroy');

    // Look up any error code (no tenant scope — for support lookups).
    Route::get('errors/{errorCode}', [TenantErrorsApiController::class, 'findByCode'])->name('api.errors.findByCode');
});
