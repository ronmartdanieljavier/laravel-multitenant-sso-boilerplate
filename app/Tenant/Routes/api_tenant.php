<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('tenant')->name('tenant.')->group(function (): void {
    // Client routes go here
});
