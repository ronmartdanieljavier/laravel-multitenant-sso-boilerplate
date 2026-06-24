<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Data\UpdateAppData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAppRequest;
use App\Models\Central\App;
use App\Repositories\Central\AppRepository;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AppManagementController extends Controller
{
    public function __construct(
        private AppRepository $appRepository,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Apps/Index', [
            'apps' => $this->appRepository->listOrdered(),
        ]);
    }

    public function update(UpdateAppRequest $request, App $app): RedirectResponse
    {
        $this->appRepository->update($app->id, UpdateAppData::from($request->validated()));

        return redirect()->route('admin.apps')->with('success', 'App updated.');
    }
}
