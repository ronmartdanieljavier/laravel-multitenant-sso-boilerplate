<?php

namespace App\Auth\Http\Controllers;

use App\Auth\Actions\LoginAction;
use App\Auth\Http\Requests\LoginRequest;
use App\Http\Controllers\Controller;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LoginController extends Controller
{
    public function __construct(
        private readonly LoginAction $loginAction,
    ) {}

    /**
     * Log the user in.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $authToken = $this->loginAction->execute($request->toData());
        } catch (AuthenticationException) {
            return response()->json(['message' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json($authToken);
    }

    /**
     * Log the user out.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
