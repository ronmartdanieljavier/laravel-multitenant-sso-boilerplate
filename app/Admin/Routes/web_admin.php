<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::get('/admin', function () {
        return Inertia::render('Admin/Index');
    })->name('admin');
});
