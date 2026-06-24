<?php

namespace App\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAppRequest;
use App\Models\Central\App;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AppManagementController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Apps/Index', [
            'apps' => App::orderBy('name')->get(['id', 'name', 'slug', 'description', 'is_active']),
        ]);
    }

    public function update(UpdateAppRequest $request, App $app): RedirectResponse
    {
        $app->update($request->validated());

        return redirect()->route('admin.apps')->with('success', 'App updated.');
    }
}
