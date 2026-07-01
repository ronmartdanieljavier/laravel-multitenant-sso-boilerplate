<?php

namespace App\Tenant\Services;

use App\Repositories\Central\UserAppRepository;
use App\Tenant\Data\TenantData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

class TenantSwitcherService
{
    public const SESSION_KEY = 'tenant_app_current_tenant';

    public function __construct(
        protected UserAppRepository $userAppRepository,
    ) {}

    /**
     * @return Collection<int, TenantData>
     */
    public function getTenantsForUser(int $userId, string $appSlug): Collection
    {
        $currentSlug = $this->getCurrentTenantSlug();

        return $this->userAppRepository
            ->getTenantsForUserAndApp($userId, $appSlug)
            ->map(fn ($tenant) => new TenantData(
                id: $tenant->id,
                name: $tenant->name,
                slug: $tenant->slug,
                isCurrent: $tenant->slug === $currentSlug,
                isMaintenance: $tenant->isMaintenance,
            ));
    }

    public function getCurrentTenantSlug(): ?string
    {
        return Session::get(self::SESSION_KEY);
    }

    public function initializeForUser(int $userId, string $appSlug): void
    {
        if (Session::has(self::SESSION_KEY)) {
            return;
        }

        $slug = $this->userAppRepository->getDefaultTenantSlugForUserAndApp($userId, $appSlug);

        if ($slug) {
            Session::put(self::SESSION_KEY, $slug);
        }
    }

    public function switchTenant(int $userId, string $appSlug, string $tenantSlug): bool
    {
        $tenants = $this->userAppRepository->getTenantsForUserAndApp($userId, $appSlug);

        $tenant = $tenants->first(fn ($t) => $t->slug === $tenantSlug);

        if (! $tenant) {
            return false;
        }

        Session::put(self::SESSION_KEY, $tenantSlug);

        return true;
    }
}
