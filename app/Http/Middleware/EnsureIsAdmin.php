<?php

namespace App\Http\Middleware;

use App\Repositories\Central\UserAppRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsAdmin
{
    public function __construct(
        protected UserAppRepository $userAppRepository,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $this->userAppRepository->userHasAdminRole($user->id)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
            }

            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
