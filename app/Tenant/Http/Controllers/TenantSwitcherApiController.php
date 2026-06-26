<?php

namespace App\Tenant\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Central\User;
use App\Tenant\Http\Requests\SwitchTenantRequest;
use App\Tenant\Services\TenantSwitcherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantSwitcherApiController extends Controller
{
    public function __construct(
        private readonly TenantSwitcherService $tenantSwitcherService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $tenants = $this->tenantSwitcherService->getTenantsForUser($user->id, 'tenant');

        return response()->json(['data' => $tenants->values()]);
    }

    public function switch(SwitchTenantRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $switched = $this->tenantSwitcherService->switchTenant(
            userId: $user->id,
            appSlug: 'tenant',
            tenantSlug: $request->string('tenant_slug')->toString(),
        );

        if (! $switched) {
            return response()->json(['message' => 'Tenant not found or access denied.'], Response::HTTP_FORBIDDEN);
        }

        return response()->json(['message' => 'Tenant switched successfully.']);
    }
}
