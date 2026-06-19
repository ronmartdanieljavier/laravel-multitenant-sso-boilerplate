<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', function () {
    return Inertia::render('Login/Index');
})->name('login');

Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
    ]);

    if (! Auth::attempt($credentials, $request->boolean('remember'))) {
        return back()->withErrors(['email' => 'These credentials do not match our records.']);
    }

    $request->session()->regenerate();

    return redirect()->intended(route('client'));
})->name('login.post');

Route::middleware('auth')->group(function () {
    Route::get('/admin', function () {
        return Inertia::render('Admin/Index');
    })->name('admin');

    Route::get('/client', function () {
        return Inertia::render('Client/Index');
    })->name('client');

    Route::get('/reports', function () {
        return Inertia::render('Reports/Index');
    })->name('reports');
});
