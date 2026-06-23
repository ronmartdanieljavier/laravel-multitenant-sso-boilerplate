<?php

namespace App\Auth\Http\Controllers;

use App\Auth\Services\AppService;
use App\Http\Controllers\Controller;
use App\Models\Central\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppPickerController extends Controller
{
    public function __construct(
        private readonly AppService $appService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $apps = $this->appService->loadApps($user->id);

        return response()->json($apps);
    }
}
