<?php

use App\Http\Middleware\ResolveWebTenantDatabase;
use App\TenantErrors\Http\Controllers\TenantErrorsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', ResolveWebTenantDatabase::class])->group(function (): void {
    Route::get('/tenant/errors', [TenantErrorsController::class, 'index'])->name('tenant.errors');
    Route::get('/tenant/errors/{errorId}', [TenantErrorsController::class, 'show'])->name('tenant.errors.show');
});
