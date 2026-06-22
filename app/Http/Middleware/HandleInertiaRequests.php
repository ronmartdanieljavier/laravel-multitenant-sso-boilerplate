<?php

namespace App\Http\Middleware;

use App\Models\Central\SystemSetting;
use App\Models\Central\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    /** @return array<string, mixed>|null */
    private function resolveAuthUser(Request $request): ?array
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'profile_picture_url' => $user->profile_picture
                ? Storage::disk('public')->url($user->profile_picture)
                : null,
        ];
    }

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => fn () => [
                'user' => $this->resolveAuthUser($request),
            ],
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
            ],
            'idleTimeoutMinutes' => fn () => $request->user()
                ? (int) SystemSetting::get('authentication_idle_time', 30)
                : null,
            'tenant' => fn () => $request->attributes->get('current_tenant'),
            'app' => fn () => $request->attributes->get('current_app'),
            'role' => fn () => $request->attributes->get('current_role'),
        ];
    }
}
