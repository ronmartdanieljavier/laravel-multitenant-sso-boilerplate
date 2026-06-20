<?php

use App\Reports\Http\Controllers\ReportController;
use App\Reports\Http\Controllers\ReportSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('reports')->name('reports.')->group(function (): void {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::post('/', [ReportController::class, 'store'])->name('store');
    Route::post('/batch', [ReportController::class, 'batch'])->name('batch');

    Route::prefix('subscriptions')->name('subscriptions.')->group(function (): void {
        Route::get('/', [ReportSubscriptionController::class, 'index'])->name('index');
        Route::post('/', [ReportSubscriptionController::class, 'store'])->name('store');
        Route::get('/{id}', [ReportSubscriptionController::class, 'show'])->name('show');
        Route::put('/{id}', [ReportSubscriptionController::class, 'update'])->name('update');
        Route::delete('/{id}', [ReportSubscriptionController::class, 'destroy'])->name('destroy');
    });

    Route::get('/{report}', [ReportController::class, 'show'])->name('show');
    Route::get('/{report}/download', [ReportController::class, 'download'])->name('download');
    Route::delete('/{report}', [ReportController::class, 'destroy'])->name('destroy');
});
