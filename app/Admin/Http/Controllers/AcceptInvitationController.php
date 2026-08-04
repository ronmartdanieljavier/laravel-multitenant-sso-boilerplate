<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Http\Requests\AcceptInvitationRequest;
use App\Admin\Services\UserManagementService;
use App\Enums\AuthenticationMessageEnum;
use App\Http\Controllers\Controller;
use App\Repositories\Central\UserRepository;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInvitationController extends Controller
{
    public function __construct(
        private UserRepository $userRepository,
        private UserManagementService $service,
    ) {}

    /**
     * Display the invitation acceptance form.
     *
     * @param  string  $token  token from the invitation link
     */
    public function show(string $token): Response|RedirectResponse
    {
        $user = $this->userRepository->findByInvitationToken($token);

        if (! $user) {
            return redirect()->route('login')->withErrors(['token' => AuthenticationMessageEnum::INVALID_INVITATION->value]);
        }

        return Inertia::render('Admin/Users/Accept', [
            'token' => $token,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    /**
     * Accept the invitation and create a new user account.
     *
     * @param  AcceptInvitationRequest  $request  credentials for the new user account
     * @param  string  $token  token from the invitation link
     */
    public function accept(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        $user = $this->userRepository->findByInvitationToken($token);

        if (! $user) {
            return redirect()->route('login')->withErrors(['token' => AuthenticationMessageEnum::INVALID_INVITATION->value]);
        }

        $this->service->acceptInvitation($user->id, $request->input('name'), $request->input('password'));

        return redirect()->route('login')->with('success', AuthenticationMessageEnum::ACCOUNT_ACTIVE->value);
    }
}
