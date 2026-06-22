<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {

    Route::get('/tenant', function () {
        return Inertia::render('Tenant/Index');
    })->name('tenant');

});
