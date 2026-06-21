<?php

use App\Http\Controllers\Admin\TenantHealthController;
use App\Http\Controllers\Auth\WebAppPickerController;
use App\Http\Controllers\Auth\WebLoginController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [WebLoginController::class, 'show'])->name('login');
Route::post('/login', [WebLoginController::class, 'login'])->name('login.post');
Route::post('/logout', [WebLoginController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/apps', [WebAppPickerController::class, 'index'])->name('apps');
    Route::post('/apps/select', [WebAppPickerController::class, 'select'])->name('apps.select');

    Route::get('/admin', function () {
        return Inertia::render('Admin/Index');
    })->name('admin');

    Route::get('/admin/tenants', [TenantHealthController::class, 'index'])->name('admin.tenants');

    Route::get('/tenant', function () {
        return Inertia::render('Tenant/Index');
    })->name('tenant');

    Route::get('/reports', function () {
        return Inertia::render('Reports/Index');
    })->name('reports');
});
