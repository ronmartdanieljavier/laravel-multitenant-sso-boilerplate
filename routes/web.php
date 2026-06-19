<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', function () {
    return Inertia::render('Login/Index');
})->name('login');

Route::get('/admin', function () {
    return Inertia::render('Admin/Index');
})->name('admin');

Route::get('/client', function () {
    return Inertia::render('Client/Index');
})->name('client');

Route::get('/reports', function () {
    return Inertia::render('Reports/Index');
})->name('reports');
