<?php

use App\Documents\Http\Controllers\DocumentApiController;
use App\Http\Middleware\ResolveTenantDatabase;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', ResolveTenantDatabase::class])
    ->prefix('documents')
    ->name('documents.')
    ->group(function (): void {
        Route::get('/', [DocumentApiController::class, 'index'])->name('index');
        Route::post('/', [DocumentApiController::class, 'store'])->name('store');
        Route::get('/{id}', [DocumentApiController::class, 'show'])->name('show');
        Route::get('/{id}/download', [DocumentApiController::class, 'download'])->name('download');
        Route::delete('/{id}', [DocumentApiController::class, 'destroy'])->name('destroy');
    });
