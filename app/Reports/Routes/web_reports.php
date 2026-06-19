<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::get('/reports', function () {
        return Inertia::render('Reports/Index');
    })->name('reports');
});
