<?php

use App\Documents\Http\Controllers\DocumentController;
use App\Http\Middleware\ResolveWebTenantDatabase;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', ResolveWebTenantDatabase::class])->group(function (): void {
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{id}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::delete('/documents/{id}', [DocumentController::class, 'destroy'])->name('documents.destroy');
});
