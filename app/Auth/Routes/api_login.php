<?php

use App\Auth\Http\Controllers\AppPickerController;
use App\Auth\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [LoginController::class, 'login'])->name('api.login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/apps', [AppPickerController::class, 'index'])->name('apps.index');
});
