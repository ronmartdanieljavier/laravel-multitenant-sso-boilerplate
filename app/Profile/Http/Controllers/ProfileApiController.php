<?php

namespace App\Profile\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Central\User;
use App\Profile\Data\ProfileData;
use App\Profile\Http\Requests\UpdateNameRequest;
use App\Profile\Http\Requests\UpdatePasswordRequest;
use App\Profile\Http\Requests\UpdatePictureRequest;
use App\Profile\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ProfileApiController extends Controller
{
    public function __construct(
        protected ProfileService $profileService
    ) {}

    /**
     * Show the user's profile.
     */
    public function show(Request $request): ProfileData
    {
        /** @var User $user */
        $user = $request->user();

        return $this->profileService->getProfile($user->id);
    }

    /**
     * Update the user's display name.
     */
    public function updateName(UpdateNameRequest $request): ProfileData
    {
        /** @var User $user */
        $user = $request->user();
        $this->profileService->updateName($user->id, $request->string('name')->toString());

        return $this->profileService->getProfile($user->id);
    }

    /**
     * Update the user's profile picture.
     */
    public function updatePicture(UpdatePictureRequest $request): ProfileData
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->profile_picture) {
            Storage::disk('public')->delete($user->profile_picture);
        }

        $path = $request->file('profile_picture')->storePublicly('profile-pictures', 'public');
        $this->profileService->updatePicture($user->id, $path);

        return $this->profileService->getProfile($user->id);
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->profileService->updatePassword($user->id, $request->string('password')->toString());

        return response()->json(['message' => 'Password updated successfully.'], Response::HTTP_OK);
    }
}
