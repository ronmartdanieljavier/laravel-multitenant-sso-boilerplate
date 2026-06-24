<?php

use App\Admin\Http\Controllers\AppManagementApiController;
use App\Admin\Http\Controllers\SystemSettingsApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('apps', [AppManagementApiController::class, 'index'])->name('api.apps');
    Route::put('apps/{app}', [AppManagementApiController::class, 'update'])->name('api.apps.update');

    Route::get('settings', [SystemSettingsApiController::class, 'index'])->name('api.settings');
    Route::put('settings', [SystemSettingsApiController::class, 'update'])->name('api.settings.update');
});
