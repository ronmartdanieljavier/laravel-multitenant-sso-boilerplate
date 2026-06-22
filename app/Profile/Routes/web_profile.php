<?php

use App\Profile\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile/name', [ProfileController::class, 'updateName'])->name('profile.name');
    Route::post('/profile/picture', [ProfileController::class, 'updatePicture'])->name('profile.picture');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
});
