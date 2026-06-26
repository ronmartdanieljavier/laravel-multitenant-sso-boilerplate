<?php

namespace App\Tenant\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Central\User;
use App\Tenant\Http\Requests\SwitchTenantRequest;
use App\Tenant\Services\TenantSwitcherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class TenantSwitcherController extends Controller
{
    public function __construct(
        private readonly TenantSwitcherService $tenantSwitcherService,
    ) {}

    public function switch(SwitchTenantRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $switched = $this->tenantSwitcherService->switchTenant(
            userId: $user->id,
            appSlug: 'tenant',
            tenantSlug: $request->string('tenant_slug')->toString(),
        );

        if (! $switched) {
            return back()->withErrors(['tenant_slug' => 'Tenant not found or access denied.']);
        }

        return redirect()->route('tenant');
    }
}
