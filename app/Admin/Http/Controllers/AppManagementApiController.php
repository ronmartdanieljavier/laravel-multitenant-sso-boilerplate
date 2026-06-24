<?php

namespace App\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAppRequest;
use App\Models\Central\App;
use Illuminate\Http\JsonResponse;

class AppManagementApiController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => App::orderBy('name')->get(['id', 'name', 'slug', 'description', 'is_active']),
        ]);
    }

    public function update(UpdateAppRequest $request, App $app): JsonResponse
    {
        $app->update($request->validated());

        return response()->json([
            'data' => $app->only(['id', 'name', 'slug', 'description', 'is_active']),
        ]);
    }
}
