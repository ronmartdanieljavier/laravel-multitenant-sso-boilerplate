<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Central\User;
use App\Models\Central\UserApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class WebLoginController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Login/Index');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'These credentials do not match our records.']);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();
        $apps = $user->userApps()->with('app')->get();

        if ($apps->count() === 1) {
            return redirect($this->resolveAppUrl($apps->first()));
        }

        return redirect()->route('apps');
    }

    private function resolveAppUrl(UserApp $userApp): string
    {
        return match ($userApp->app->slug) {
            'admin' => route('admin'),
            'tenant' => route('tenant'),
            default => route('admin'),
        };
    }
}
