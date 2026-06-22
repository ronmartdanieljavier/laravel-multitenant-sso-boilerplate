<?php

use App\Auth\Http\Controllers\WebAppPickerController;
use App\Auth\Http\Controllers\WebLoginController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [WebLoginController::class, 'show'])->name('login');
Route::post('/login', [WebLoginController::class, 'login'])->name('login.post');
Route::post('/logout', [WebLoginController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/apps', [WebAppPickerController::class, 'index'])->name('apps');
    Route::post('/apps/select', [WebAppPickerController::class, 'select'])->name('apps.select');
});
