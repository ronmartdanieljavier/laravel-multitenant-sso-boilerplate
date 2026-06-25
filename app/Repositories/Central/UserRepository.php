<?php

namespace App\Repositories\Central;

use App\Data\Repositories\Central\UserAppRepositoryData;
use App\Data\Repositories\Central\UserRepositoryData;
use App\Data\Repositories\Central\UserWithPermissionsRepositoryData;
use App\Models\Central\User;
use App\Models\Central\UserApp;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserRepository
{
    public function __construct(
        protected User $model
    ) {}

    public function find(int $id): UserRepositoryData
    {
        $user = $this->model->findOrFail($id);

        return UserRepositoryData::from($user);
    }

    /**
     * @return Collection<int, UserRepositoryData>
     */
    public function list(): Collection
    {
        return $this->model->get()->map(fn (User $user) => UserRepositoryData::from($user));
    }

    /**
     * @return Collection<int, UserWithPermissionsRepositoryData>
     */
    public function listWithPermissions(): Collection
    {
        return $this->model
            ->with(['userApps.app:id,name,slug', 'userAppTenants'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->toWithPermissions($user));
    }

    /**
     * @return Collection<int, UserWithPermissionsRepositoryData>
     */
    public function listForTenant(int $tenantId): Collection
    {
        return $this->model
            ->with(['userApps.app:id,name,slug', 'userAppTenants'])
            ->whereHas('userAppTenants', fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->toWithPermissions($user));
    }

    public function findWithPermissions(int $id): UserWithPermissionsRepositoryData
    {
        $user = $this->model
            ->with(['userApps.app:id,name,slug', 'userAppTenants'])
            ->findOrFail($id);

        return $this->toWithPermissions($user);
    }

    public function findByInvitationToken(string $token): ?UserRepositoryData
    {
        $user = $this->model
            ->where('invitation_token', $token)
            ->where('is_active', false)
            ->first();

        return $user ? UserRepositoryData::from($user) : null;
    }

    public function createInvited(string $name, string $email): UserRepositoryData
    {
        $user = $this->model->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
            'is_active' => false,
            'invitation_token' => Str::random(64),
            'invitation_sent_at' => now(),
        ]);

        return UserRepositoryData::from($user);
    }

    public function updateProfile(int $id, string $name, string $email): void
    {
        $user = $this->model->findOrFail($id);
        $user->update(['name' => $name, 'email' => $email]);
    }

    public function activateInvitation(int $id, string $name, string $password): void
    {
        $user = $this->model->findOrFail($id);
        $user->update([
            'name' => $name,
            'password' => Hash::make($password),
            'is_active' => true,
            'invitation_token' => null,
            'email_verified_at' => now(),
        ]);
    }

    public function updateName(int $id, string $name): UserRepositoryData
    {
        $user = $this->model->findOrFail($id);
        $user->update(['name' => $name]);

        return UserRepositoryData::from($user->fresh());
    }

    public function updatePicture(int $id, string $path): UserRepositoryData
    {
        $user = $this->model->findOrFail($id);
        $user->update(['profile_picture' => $path]);

        return UserRepositoryData::from($user->fresh());
    }

    public function updatePassword(int $id, string $password): void
    {
        $user = $this->model->findOrFail($id);
        $user->update(['password' => Hash::make($password)]);
    }

    public function findByEmail(string $email): ?UserRepositoryData
    {
        $user = $this->model->where('email', $email)->first();

        return $user ? UserRepositoryData::from($user) : null;
    }

    /** @param Collection<int, int> $userIds */
    public function revokeTokensForUsers(Collection $userIds): void
    {
        if ($userIds->isEmpty()) {
            return;
        }

        $this->model->whereIn('id', $userIds)->each(function (User $user): void {
            $user->tokens()->delete();
        });
    }

    public function verifyPassword(int $id, string $password): bool
    {
        $user = $this->model->findOrFail($id);

        return Hash::check($password, $user->password);
    }

    /**
     * @param  array<int, string>  $abilities
     */
    public function createSanctumToken(int $id, string $name, array $abilities): string
    {
        $user = $this->model->findOrFail($id);

        return $user->createToken($name, $abilities)->plainTextToken;
    }

    private function toWithPermissions(User $user): UserWithPermissionsRepositoryData
    {
        return new UserWithPermissionsRepositoryData(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            isActive: $user->is_active,
            invitationSentAt: $user->invitation_sent_at,
            profilePictureUrl: $user->profile_picture_url,
            apps: $user->userApps->map(fn (UserApp $ua) => new UserAppRepositoryData(
                appId: $ua->app_id,
                appName: $ua->app->name,
                role: $ua->role->value,
                tenantIds: $user->userAppTenants
                    ->where('app_id', $ua->app_id)
                    ->pluck('tenant_id')
                    ->values()
                    ->all(),
            ))->all(),
        );
    }
}
