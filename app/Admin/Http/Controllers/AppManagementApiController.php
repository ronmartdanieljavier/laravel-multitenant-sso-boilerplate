<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Data\UpdateAppData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAppRequest;
use App\Models\Central\App;
use App\Repositories\Central\AppRepository;
use Illuminate\Http\JsonResponse;

class AppManagementApiController extends Controller
{
    public function __construct(
        private AppRepository $appRepository,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->appRepository->listOrdered(),
        ]);
    }

    public function update(UpdateAppRequest $request, App $app): JsonResponse
    {
        $updated = $this->appRepository->update($app->id, UpdateAppData::from($request->validated()));

        return response()->json(['data' => $updated]);
    }
}
