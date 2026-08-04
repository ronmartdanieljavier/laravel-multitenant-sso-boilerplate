<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Data\UserAppData;
use App\Admin\Data\UserData;
use App\Data\Repositories\Central\UserWithPermissionsRepositoryData;
use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Repositories\Central\AppRepository;
use App\Repositories\Central\TenantRepository;
use App\Repositories\Central\UserRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class TenantUsersController extends Controller
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly AppRepository $appRepository,
        private readonly TenantRepository $tenantRepository,
    ) {}

    /**
     * List all users that belong to the given tenant.
     *
     * @param  Tenant  $tenant  the tenant for which to list users
     */
    public function index(Tenant $tenant): Response
    {
        $loggedInIds = $this->tenantRepository->loggedInUserIdsForTenant($tenant->id);

        return Inertia::render('Admin/Tenants/Users', [
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug],
            'users' => $this->listUsersForTenant($tenant->id, $loggedInIds),
            'apps' => $this->appRepository->listOrdered(),
            'tenants' => $this->tenantRepository->listOrdered(),
        ]);
    }

    /**
     * Force logout a specific user from this tenant by revoking their tokens.
     *
     * @param  Tenant  $tenant  the tenant for which to force logout the user
     * @param  User  $user  the user to be logged out
     */
    public function forceLogout(Tenant $tenant, User $user): RedirectResponse
    {
        $this->userRepository->revokeTokensForUser($user->id);

        return redirect()->route('admin.tenants.users', $tenant)
            ->with('success', "{$user->name} has been logged out.");
    }

    /**
     * List all users for a specific tenant, including their logged-in status.
     *
     * @param  int  $tenantId  the ID of the tenant for which to list
     * @param  Collection<int, int>  $loggedInIds  a collection of user IDs that are currently logged in for the tenant
     * @return Collection<int, UserData>
     */
    private function listUsersForTenant(int $tenantId, Collection $loggedInIds): Collection
    {
        return $this->userRepository->listForTenant($tenantId)
            ->map(fn (UserWithPermissionsRepositoryData $dto) => new UserData(
                id: $dto->id,
                name: $dto->name,
                email: $dto->email,
                isActive: $dto->isActive,
                isLoggedIn: $loggedInIds->contains($dto->id),
                invitationSentAt: $dto->invitationSentAt,
                profilePictureUrl: $dto->profilePictureUrl,
                createdAt: $dto->createdAt,
                apps: array_map(fn ($a) => new UserAppData(
                    appId: $a->appId,
                    appName: $a->appName,
                    role: $a->role,
                    tenantIds: $a->tenantIds,
                ), $dto->apps),
            ))
            ->values();
    }
}
