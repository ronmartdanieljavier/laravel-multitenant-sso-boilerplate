<?php

namespace App\Auth\Http\Controllers;

use App\Auth\Http\Requests\WebLoginRequest;
use App\Auth\Services\AppService;
use App\Http\Controllers\Controller;
use App\Models\Central\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class WebLoginController extends Controller
{
    public function __construct(
        private readonly AppService $appService,
    ) {}

    /**
     * Show the login form.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return $this->redirectBasedOnApps($request->user());
        }

        return Inertia::render('Login/Index');
    }

    /**
     * Log the user in.
     */
    public function login(WebLoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'These credentials do not match our records.']);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        return $this->redirectBasedOnApps($user);
    }

    /**
     * Log the user out.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Redirect the user based on their app access.
     */
    private function redirectBasedOnApps(User $user): RedirectResponse
    {
        $apps = $this->appService->loadApps($user->id);

        if ($apps->toCollection()->count() === 1) {
            $slug = $apps->toCollection()->first()->slug;

            return redirect($this->resolveRouteForSlug($slug));
        }

        return redirect()->route('apps');
    }

    /**
     * Resolve the route for a given app slug.
     */
    private function resolveRouteForSlug(string $slug): string
    {
        return match ($slug) {
            'admin' => route('admin'),
            'tenant' => route('tenant'),
            default => route('admin'),
        };
    }
}
