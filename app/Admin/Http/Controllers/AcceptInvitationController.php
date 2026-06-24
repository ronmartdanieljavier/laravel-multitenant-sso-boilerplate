<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Http\Requests\AcceptInvitationRequest;
use App\Admin\Services\UserManagementService;
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

    public function show(string $token): Response|RedirectResponse
    {
        $user = $this->userRepository->findByInvitationToken($token);

        if (! $user) {
            return redirect()->route('login')->withErrors(['token' => 'This invitation link is invalid or has already been used.']);
        }

        return Inertia::render('Admin/Users/Accept', [
            'token' => $token,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    public function accept(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        $user = $this->userRepository->findByInvitationToken($token);

        if (! $user) {
            return redirect()->route('login')->withErrors(['token' => 'This invitation link is invalid or has already been used.']);
        }

        $this->service->acceptInvitation($user->id, $request->input('name'), $request->input('password'));

        return redirect()->route('login')->with('success', 'Your account is active. Please sign in.');
    }
}
