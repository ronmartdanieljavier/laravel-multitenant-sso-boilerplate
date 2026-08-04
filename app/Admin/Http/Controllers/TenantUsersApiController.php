<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Data\UserAppData;
use App\Admin\Data\UserData;
use App\Data\Repositories\Central\UserWithPermissionsRepositoryData;
use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Repositories\Central\TenantRepository;
use App\Repositories\Central\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class TenantUsersApiController extends Controller
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TenantRepository $tenantRepository,
    ) {}

    /**
     * List all users that belong to the given tenant.
     *
     * @param  Tenant  $tenant  the tenant for which to list users
     */
    public function index(Tenant $tenant): JsonResponse
    {
        $loggedInIds = $this->tenantRepository->loggedInUserIdsForTenant($tenant->id);

        return response()->json(['data' => $this->listUsersForTenant($tenant->id, $loggedInIds)]);
    }

    /**
     * Force logout a specific user from this tenant by revoking their tokens.
     *
     * @param  Tenant  $tenant  the tenant for which to force logout the user
     * @param  User  $user  the user to be logged out
     */
    public function forceLogout(Tenant $tenant, User $user): JsonResponse
    {
        $this->userRepository->revokeTokensForUser($user->id);

        return response()->json(['message' => "{$user->name} has been logged out."]);
    }

    /**
     * List all users for a specific tenant, including their logged-in status.
     *
     * @param  int  $tenantId  the ID of the tenant for which to list users
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
