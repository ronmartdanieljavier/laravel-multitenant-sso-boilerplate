<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Data\UserAppData;
use App\Admin\Data\UserData;
use App\Data\Repositories\Central\UserWithPermissionsRepositoryData;
use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Repositories\Central\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class TenantUsersApiController extends Controller
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}

    /**
     * List all users that belong to the given tenant.
     */
    public function index(Tenant $tenant): JsonResponse
    {
        return response()->json(['data' => $this->listUsersForTenant($tenant->id)]);
    }

    /**
     * @return Collection<int, UserData>
     */
    private function listUsersForTenant(int $tenantId): Collection
    {
        return $this->userRepository->listForTenant($tenantId)
            ->map(fn (UserWithPermissionsRepositoryData $dto) => new UserData(
                id: $dto->id,
                name: $dto->name,
                email: $dto->email,
                isActive: $dto->isActive,
                invitationSentAt: $dto->invitationSentAt,
                profilePictureUrl: $dto->profilePictureUrl,
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
