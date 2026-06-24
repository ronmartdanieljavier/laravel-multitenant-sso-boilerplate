<?php

use App\Admin\Http\Controllers\AcceptInvitationController;
use App\Admin\Http\Controllers\AppManagementController;
use App\Admin\Http\Controllers\SystemSettingsController;
use App\Admin\Http\Controllers\TenantManagementController;
use App\Admin\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/invitation/{token}', [AcceptInvitationController::class, 'show'])->name('invitation.accept');
Route::post('/invitation/{token}', [AcceptInvitationController::class, 'accept'])->name('invitation.accept.submit');

Route::middleware('auth')->group(function () {

    Route::get('/admin', function () {
        return Inertia::render('Admin/Index');
    })->name('admin');

    Route::get('/admin/users', [UserManagementController::class, 'index'])->name('admin.users');
    Route::post('/admin/users/invite', [UserManagementController::class, 'invite'])->name('admin.users.invite');
    Route::put('/admin/users/{user}', [UserManagementController::class, 'update'])->name('admin.users.update');

    Route::get('/admin/apps', [AppManagementController::class, 'index'])->name('admin.apps');
    Route::put('/admin/apps/{app}', [AppManagementController::class, 'update'])->name('admin.apps.update');

    Route::get('/admin/tenants', [TenantManagementController::class, 'index'])->name('admin.tenants');
    Route::post('/admin/tenants/migrate-all', [TenantManagementController::class, 'migrateAll'])->name('admin.tenants.migrateAll');
    Route::post('/admin/tenants', [TenantManagementController::class, 'store'])->name('admin.tenants.store');
    Route::put('/admin/tenants/{tenant}', [TenantManagementController::class, 'update'])->name('admin.tenants.update');
    Route::patch('/admin/tenants/{tenant}/active', [TenantManagementController::class, 'setActive'])->name('admin.tenants.setActive');
    Route::post('/admin/tenants/{tenant}/migrate', [TenantManagementController::class, 'migrate'])->name('admin.tenants.migrate');
    Route::delete('/admin/tenants/{tenant}', [TenantManagementController::class, 'destroy'])->name('admin.tenants.destroy');

    Route::get('/admin/settings', [SystemSettingsController::class, 'index'])->name('admin.settings');
    Route::put('/admin/settings', [SystemSettingsController::class, 'update'])->name('admin.settings.update');
});
