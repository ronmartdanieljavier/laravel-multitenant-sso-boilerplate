<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

foreach (glob(app_path('*/Routes/web_*.php')) as $routeFile) {
    require $routeFile;
}

Route::get('/', function () {
    return Inertia::render('Landing/Index');
})->name('home');
