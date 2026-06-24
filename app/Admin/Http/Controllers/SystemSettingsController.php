<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Services\SystemSettingsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSystemSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingsController extends Controller
{
    public function __construct(
        private readonly SystemSettingsService $systemSettingsService,
    ) {}

    /**
     * Display the system settings.
     */
    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => $this->systemSettingsService->getSettings(),
        ]);
    }

    /**
     * Update the system settings.
     */
    public function update(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        $this->systemSettingsService->updateSettings($request->validated());

        return redirect()->route('admin.settings')->with('success', 'Settings saved.');
    }
}
