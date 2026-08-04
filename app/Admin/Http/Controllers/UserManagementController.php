<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Data\InviteUserData;
use App\Admin\Data\UpdateUserData;
use App\Admin\Http\Requests\InviteUserRequest;
use App\Admin\Http\Requests\UpdateUserRequest;
use App\Admin\Services\UserManagementService;
use App\Auth\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Central\User;
use App\Repositories\Central\AppRepository;
use App\Repositories\Central\TenantRepository;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function __construct(
        private AppRepository $appRepository,
        private TenantRepository $tenantRepository,
        private UserManagementService $service,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => $this->service->list(),
            'apps' => $this->appRepository->listOrdered(),
            'tenants' => $this->tenantRepository->listOrdered(),
            'roles' => array_column(Role::cases(), 'value'),
        ]);
    }

    /**
     * Invite a new user.
     *
     * @param  InviteUserRequest  $request  validated request data
     */
    public function invite(InviteUserRequest $request): RedirectResponse
    {
        $this->service->invite(InviteUserData::from($request->validated()));

        return redirect()->route('admin.users')->with('success', 'Invitation sent.');
    }

    /**
     * Update a user's information.
     *
     * @param  UpdateUserRequest  $request  validated request data
     * @param  User  $user  the user to be updated
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->service->update($user->id, UpdateUserData::from($request->validated()));

        return redirect()->route('admin.users')->with('success', 'User updated.');
    }

    /**
     * Resend the invitation email to a pending user.
     *
     * @param  User  $user  the user to whom the invitation will be resent
     */
    public function resendInvitation(User $user): RedirectResponse
    {
        $this->service->resendInvitation($user->id);

        return redirect()->back()->with('success', 'Invitation resent.');
    }
}
