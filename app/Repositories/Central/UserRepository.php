<?php

namespace App\Repositories\Central;

use App\Data\Repositories\Central\UserRepositoryData;
use App\Models\Central\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class UserRepository
{
    public function __construct(
        protected User $model
    ) {}

    /**
     * Find a user by ID and return a UserRepositoryData instance.
     *
     * @param  int  $id  The ID of the user to find.
     */
    public function find(int $id): UserRepositoryData
    {
        $user = $this->model->findOrFail($id);

        return UserRepositoryData::from($user);
    }

    /**
     * List all users and return a collection of UserRepositoryData instances.
     */
    public function list(): Collection
    {
        $users = $this->model->get();

        return $users->map(fn (User $user) => UserRepositoryData::from($user));
    }

    /**
     * Update a user's name and return the updated UserRepositoryData instance.
     *
     * @param  int  $id  The ID of the user to update.
     * @param  string  $name  The new name for the user.
     */
    public function updateName(int $id, string $name): UserRepositoryData
    {
        $user = $this->model->findOrFail($id);
        $user->update(['name' => $name]);

        return UserRepositoryData::from($user->fresh());
    }

    /**
     * Update a user's profile picture and return the updated UserRepositoryData instance.
     *
     * @param  int  $id  The ID of the user to update.
     * @param  string  $path  The new profile picture path for the user.
     */
    public function updatePicture(int $id, string $path): UserRepositoryData
    {
        $user = $this->model->findOrFail($id);
        $user->update(['profile_picture' => $path]);

        return UserRepositoryData::from($user->fresh());
    }

    /**
     * Update a user's password and return the updated UserRepositoryData instance.
     *
     * @param  int  $id  The ID of the user to update.
     * @param  string  $password  The new password for the user.
     */
    public function updatePassword(int $id, string $password): void
    {
        $user = $this->model->findOrFail($id);
        $user->update(['password' => Hash::make($password)]);
    }
}
