<?php

namespace App\Profile\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Central\User;
use App\Profile\Http\Requests\UpdateNameRequest;
use App\Profile\Http\Requests\UpdatePasswordRequest;
use App\Profile\Http\Requests\UpdatePictureRequest;
use App\Profile\Services\ProfileService;
use App\Storage\StorageResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        protected ProfileService $profileService,
        private readonly StorageResolver $resolver,
    ) {}

    /**
     * Show the user's profile page.
     */
    public function show(): Response
    {
        return Inertia::render('Profile/Index');
    }

    /**
     * Update the user's display name.
     */
    public function updateName(UpdateNameRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->profileService->updateName($user->id, $request->string('name')->toString());

        return back()->with('success', 'Display name updated.');
    }

    /**
     * Update the user's profile picture.
     */
    public function updatePicture(UpdatePictureRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $disk = $this->resolver->forSystem();

        if ($user->profile_picture) {
            $disk->delete($user->profile_picture);
        }

        $file = $request->file('profile_picture');
        $path = 'profile-pictures/'.$file->hashName();
        $disk->put($path, $file->get(), 'public');
        $this->profileService->updatePicture($user->id, $path);

        return back()->with('success', 'Profile picture updated.');
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $this->profileService->updatePassword($user->id, $request->string('password')->toString());

        return back()->with('success', 'Password updated.');
    }
}
