<?php

namespace App\Http\Middleware;

use App\Auth\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    /**
     * @var array<int, Role>
     */
    private const array HIERARCHY = [Role::Admin, Role::User, Role::Readonly];

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $currentRole = $request->attributes->get('current_role');

        if (! $currentRole instanceof Role) {
            return response()->json(['message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
        }

        $requiredRoles = array_map(fn (string $role) => Role::from($role), $roles);

        $userRank = array_search($currentRole, self::HIERARCHY, strict: true);

        foreach ($requiredRoles as $required) {
            $requiredRank = array_search($required, self::HIERARCHY, strict: true);

            if ($userRank <= $requiredRank) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
    }
}
