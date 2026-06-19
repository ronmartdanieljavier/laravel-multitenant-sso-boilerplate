<?php

namespace App\Login\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Login\Actions\LoadUserAppsAction;
use App\Login\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppPickerController extends Controller
{
    public function __construct(
        private readonly LoadUserAppsAction $loadUserAppsAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $apps = $this->loadUserAppsAction->execute($user);

        return response()->json($apps);
    }
}
