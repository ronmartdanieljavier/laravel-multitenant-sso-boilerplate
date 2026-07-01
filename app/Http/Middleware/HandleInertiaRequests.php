<?php

namespace App\Http\Middleware;

use App\Admin\Services\SystemSettingsService;
use App\Models\Central\User;
use App\Repositories\Central\SystemSettingRepository;
use App\Storage\StorageResolver;
use App\Tenant\Services\TenantSwitcherService;
use Illuminate\Http\Request;
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

    public function __construct(
        private readonly StorageResolver $resolver,
    ) {}

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
                ? $this->resolver->forSystem()->url($user->profile_picture)
                : null,
        ];
    }

    /** @return list<array{id: int, name: string, slug: string, isCurrent: bool}>|null */
    private function resolveAvailableTenants(Request $request): ?array
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || $request->attributes->get('current_app') === null) {
            return null;
        }

        $tenants = app(TenantSwitcherService::class)->getTenantsForUser($user->id, 'tenant');

        if ($tenants->count() <= 1) {
            return null;
        }

        return $tenants->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'slug' => $t->slug,
            'isCurrent' => $t->isCurrent,
        ])->values()->all();
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
                ? (int) app(SystemSettingRepository::class)->get('authentication_idle_time', 30)
                : null,
            'missingRequiredSettings' => fn () => $request->user()
                ? app(SystemSettingsService::class)->getMissingRequiredSettings()->labels
                : [],
            'tenant' => fn () => $request->attributes->get('current_tenant'),
            'app' => fn () => $request->attributes->get('current_app'),
            'role' => fn () => $request->attributes->get('current_role'),
            'availableTenants' => fn () => $this->resolveAvailableTenants($request),
        ];
    }
}
