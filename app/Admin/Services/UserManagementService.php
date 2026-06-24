<?php

namespace App\Admin\Services;

use App\Admin\Data\InviteUserData;
use App\Admin\Data\UpdateUserData;
use App\Admin\Data\UserAppData;
use App\Admin\Data\UserData;
use App\Admin\Mail\UserInvitationMail;
use App\Data\Repositories\Central\UserAppRepositoryData;
use App\Data\Repositories\Central\UserWithPermissionsRepositoryData;
use App\Repositories\Central\UserAppRepository;
use App\Repositories\Central\UserRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class UserManagementService
{
    public function __construct(
        protected UserRepository $userRepository,
        protected UserAppRepository $userAppRepository,
    ) {}

    /**
     * @return Collection<int, UserData>
     */
    public function list(): Collection
    {
        return $this->userRepository->listWithPermissions()
            ->map(fn (UserWithPermissionsRepositoryData $dto) => $this->toData($dto))
            ->values();
    }

    public function invite(InviteUserData $data): UserData
    {
        $created = DB::transaction(function () use ($data) {
            $created = $this->userRepository->createInvited($data->name, $data->email);

            $this->userAppRepository->syncPermissions($created->id, $data->apps);

            Mail::to($created->email)->send(new UserInvitationMail($created));

            return $created;
        });

        return $this->toData($this->userRepository->findWithPermissions($created->id));
    }

    public function update(int $userId, UpdateUserData $data): UserData
    {
        DB::transaction(function () use ($userId, $data) {
            $this->userRepository->updateProfile($userId, $data->name, $data->email);

            $this->userAppRepository->syncPermissions($userId, $data->apps);
        });

        return $this->toData($this->userRepository->findWithPermissions($userId));
    }

    public function acceptInvitation(int $userId, string $name, string $password): UserData
    {
        $this->userRepository->activateInvitation($userId, $name, $password);

        return $this->toData($this->userRepository->findWithPermissions($userId));
    }

    private function toData(UserWithPermissionsRepositoryData $dto): UserData
    {
        return new UserData(
            id: $dto->id,
            name: $dto->name,
            email: $dto->email,
            isActive: $dto->isActive,
            invitationSentAt: $dto->invitationSentAt,
            profilePictureUrl: $dto->profilePictureUrl,
            apps: array_map(fn (UserAppRepositoryData $ua) => new UserAppData(
                appId: $ua->appId,
                appName: $ua->appName,
                role: $ua->role,
                tenantIds: $ua->tenantIds,
            ), $dto->apps),
        );
    }
}
