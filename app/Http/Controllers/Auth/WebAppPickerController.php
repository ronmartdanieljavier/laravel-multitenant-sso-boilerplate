<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Central\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class WebAppPickerController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $apps = $user->userApps()->with('app')->get()->map(fn ($ua) => [
            'slug' => $ua->app->slug,
            'name' => $ua->app->name,
            'description' => $ua->app->description,
            'role' => $ua->role->value,
        ])->values();

        if ($apps->isEmpty()) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/AppPicker', [
            'apps' => $apps,
            'user' => ['name' => $user->name, 'email' => $user->email],
        ]);
    }

    public function select(Request $request): RedirectResponse
    {
        $request->validate(['slug' => ['required', 'string']]);

        $slug = $request->input('slug');

        /** @var User $user */
        $user = Auth::user();
        $hasAccess = $user->userApps()->with('app')
            ->get()
            ->contains(fn ($ua) => $ua->app->slug === $slug);

        if (! $hasAccess) {
            return back()->withErrors(['slug' => 'You do not have access to this app.']);
        }

        return match ($slug) {
            'admin' => redirect()->route('admin'),
            'tenant' => redirect()->route('tenant'),
            default => redirect()->route('admin'),
        };
    }
}
