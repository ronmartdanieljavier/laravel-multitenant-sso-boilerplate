<?php

use App\Profile\Http\Controllers\ProfileApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('profile')->name('api.profile.')->group(function (): void {
    Route::get('/', [ProfileApiController::class, 'show'])->name('show');
    Route::put('/name', [ProfileApiController::class, 'updateName'])->name('name');
    Route::post('/picture', [ProfileApiController::class, 'updatePicture'])->name('picture');
    Route::put('/password', [ProfileApiController::class, 'updatePassword'])->name('password');
});
