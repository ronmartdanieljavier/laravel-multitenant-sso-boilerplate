<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Data\UserAppData;
use App\Admin\Data\UserData;
use App\Data\Repositories\Central\UserWithPermissionsRepositoryData;
use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Repositories\Central\AppRepository;
use App\Repositories\Central\TenantRepository;
use App\Repositories\Central\UserRepository;
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
     */
    public function index(Tenant $tenant): Response
    {
        return Inertia::render('Admin/Tenants/Users', [
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug],
            'users' => $this->listUsersForTenant($tenant->id),
            'apps' => $this->appRepository->listOrdered(),
            'tenants' => $this->tenantRepository->listOrdered(),
        ]);
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
