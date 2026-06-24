<?php

use App\Admin\Http\Controllers\AppManagementApiController;
use App\Admin\Http\Controllers\SystemSettingsApiController;
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
});
