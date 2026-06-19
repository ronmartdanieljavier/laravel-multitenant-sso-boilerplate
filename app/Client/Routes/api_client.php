<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('client')->name('client.')->group(function (): void {
    // Client routes go here
});
