<?php

namespace App\Auth\Http\Controllers;

use App\Auth\Http\Requests\SelectAppRequest;
use App\Auth\Services\AppService;
use App\Http\Controllers\Controller;
use App\Models\Central\User;
use App\Tenant\Services\TenantSwitcherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class WebAppPickerController extends Controller
{
    public function __construct(
        private readonly AppService $appService,
        private readonly TenantSwitcherService $tenantSwitcherService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): Response|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $apps = $this->appService->loadApps($user->id);

        if ($apps->toCollection()->isEmpty()) {
            return redirect()->route('login');
        }

        $appsForView = $apps->toCollection()->map(fn ($app) => [
            'slug' => $app->slug,
            'name' => $app->name,
            'description' => $app->description,
            'role' => $app->role->value,
        ])->values();

        return Inertia::render('Auth/AppPicker', [
            'apps' => $appsForView,
            'user' => ['name' => $user->name, 'email' => $user->email],
        ]);
    }

    /**
     * Select an app for the user.
     */
    public function select(SelectAppRequest $request): RedirectResponse
    {
        $slug = $request->string('slug')->toString();

        /** @var User $user */
        $user = Auth::user();
        $apps = $this->appService->loadApps($user->id);

        $hasAccess = $apps->toCollection()->contains(fn ($app) => $app->slug === $slug);

        if (! $hasAccess) {
            return back()->withErrors(['slug' => 'You do not have access to this app.']);
        }

        if ($slug === 'tenant') {
            $this->tenantSwitcherService->initializeForUser($user->id, 'tenant');
        }

        return match ($slug) {
            'admin' => redirect()->route('admin'),
            'tenant' => redirect()->route('tenant'),
            default => redirect()->route('admin'),
        };
    }
}
