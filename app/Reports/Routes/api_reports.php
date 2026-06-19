<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('reports')->name('reports.')->group(function (): void {
    // Reports routes go here
});
