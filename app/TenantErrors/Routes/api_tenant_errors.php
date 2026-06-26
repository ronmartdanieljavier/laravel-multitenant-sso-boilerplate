<?php

use App\Http\Middleware\ResolveTenantDatabase;
use App\TenantErrors\Http\Controllers\TenantErrorsApiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', ResolveTenantDatabase::class])
    ->prefix('tenant/errors')
    ->name('tenant.api.errors.')
    ->group(function (): void {
        Route::get('/', [TenantErrorsApiController::class, 'index'])->name('index');
        Route::get('/{errorId}', [TenantErrorsApiController::class, 'show'])->name('show');
    });
