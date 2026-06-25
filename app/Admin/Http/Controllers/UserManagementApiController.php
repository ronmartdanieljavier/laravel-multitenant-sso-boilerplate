<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Data\InviteUserData;
use App\Admin\Data\UpdateUserData;
use App\Admin\Http\Requests\InviteUserRequest;
use App\Admin\Http\Requests\UpdateUserRequest;
use App\Admin\Services\UserManagementService;
use App\Http\Controllers\Controller;
use App\Models\Central\User;
use Illuminate\Http\JsonResponse;

class UserManagementApiController extends Controller
{
    public function __construct(
        private UserManagementService $service,
    ) {}

    /**
     * List all users.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->list(),
        ]);
    }

    /**
     * Invite a new user.
     */
    public function invite(InviteUserRequest $request): JsonResponse
    {
        $userData = $this->service->invite(InviteUserData::from($request->validated()));

        return response()->json(['data' => $userData], 201);
    }

    /**
     * Update a user's information.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $userData = $this->service->update($user->id, UpdateUserData::from($request->validated()));

        return response()->json(['data' => $userData]);
    }

    /**
     * Resend the invitation email to a pending user.
     */
    public function resendInvitation(User $user): JsonResponse
    {
        $this->service->resendInvitation($user->id);

        return response()->json(['message' => 'Invitation resent.']);
    }
}
