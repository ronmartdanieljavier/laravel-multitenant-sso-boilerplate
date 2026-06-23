<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Services\SystemSettingsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSystemSettingsRequest;
use Illuminate\Http\JsonResponse;

class SystemSettingsApiController extends Controller
{
    public function __construct(
        private readonly SystemSettingsService $systemSettingsService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->systemSettingsService->getSettings(),
            'missing_required' => $this->systemSettingsService->getMissingRequiredSettings(),
        ]);
    }

    public function update(UpdateSystemSettingsRequest $request): JsonResponse
    {
        $this->systemSettingsService->updateSettings($request->validated());

        return response()->json([
            'data' => $this->systemSettingsService->getSettings(),
            'missing_required' => $this->systemSettingsService->getMissingRequiredSettings(),
        ]);
    }
}
