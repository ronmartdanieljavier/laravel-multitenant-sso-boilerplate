<?php

namespace App\Profile\Services;

use App\Profile\Data\ProfileData;
use App\Repositories\Central\UserRepository;

class ProfileService
{
    public function __construct(
        protected UserRepository $userRepository
    ) {}

    /**
     * Get user profile informations
     *
     * @param  int  $userId  The ID of the user to get profile information for.
     */
    public function getProfile(int $userId): ProfileData
    {
        return ProfileData::from($this->userRepository->find($userId));
    }

    /**
     * Update user's name
     *
     * @param  int  $userId  The ID of the user to update.
     * @param  string  $name  The new name for the user.
     */
    public function updateName(int $userId, string $name): ProfileData
    {
        return ProfileData::from($this->userRepository->updateName($userId, $name));
    }

    /**
     * Update user's profile picture
     *
     * @param  int  $userId  The ID of the user to update.
     * @param  string  $path  The path to the new profile picture.
     */
    public function updatePicture(int $userId, string $path): ProfileData
    {
        return ProfileData::from($this->userRepository->updatePicture($userId, $path));
    }

    /**
     * Update user's password
     *
     * @param  int  $userId  The ID of the user to update.
     * @param  string  $password  The new password for the user.
     */
    public function updatePassword(int $userId, string $password): void
    {
        $this->userRepository->updatePassword($userId, $password);
    }
}
