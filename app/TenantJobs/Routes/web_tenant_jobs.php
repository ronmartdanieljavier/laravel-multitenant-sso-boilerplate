<?php

use App\Http\Middleware\ResolveWebTenantDatabase;
use App\TenantJobs\Http\Controllers\TenantJobsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', ResolveWebTenantDatabase::class])->group(function (): void {
    Route::get('/tenant/jobs', [TenantJobsController::class, 'index'])->name('tenant.jobs');
});
