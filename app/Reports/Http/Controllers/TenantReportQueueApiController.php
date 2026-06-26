<?php

namespace App\Reports\Http\Controllers;

use App\Admin\Services\TenantSettingsService;
use App\Http\Controllers\Controller;
use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportStatus;
use App\Reports\Http\Requests\QuickReportRequest;
use App\Reports\Jobs\GenerateReportJob;
use App\Repositories\Central\ReportRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TenantReportQueueApiController extends Controller
{
    public function __construct(
        private readonly ReportRepository $reportRepository,
        private readonly TenantSettingsService $tenantSettingsService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('current_tenant');

        abort_if($tenant === null, 404);

        $reports = $this->reportRepository->listForTenant($tenant->id);

        return response()->json(['data' => $reports]);
    }

    public function retry(Request $request, string $reportId): JsonResponse
    {
        $tenant = $request->attributes->get('current_tenant');

        abort_if($tenant === null, 404);

        $settings = $this->tenantSettingsService->getSettings($tenant->id);

        $dto = $this->reportRepository->resetForRetry($reportId);

        abort_if($dto->tenantId !== $tenant->id, 403);

        $job = new GenerateReportJob(
            $dto->id,
            $settings->reportQueue ?: 'reports',
            $settings->reportTimeout ? (int) $settings->reportTimeout : null,
            $settings->reportConnection ?: null,
        );
        GenerateReportJob::initTracking($job, $tenant->id, $request->user()->id, 'Report: '.$dto->type);
        dispatch($job);

        return response()->json(['data' => $dto]);
    }

    public function quickDispatch(QuickReportRequest $request): JsonResponse
    {
        $tenant = $request->attributes->get('current_tenant');

        abort_if($tenant === null, 404);

        $settings = $this->tenantSettingsService->getSettings($tenant->id);

        $dto = $this->reportRepository->create([
            'user_id' => $request->user()->id,
            'tenant_id' => $tenant->id,
            'type' => $request->input('type'),
            'format' => ReportFormat::from($request->input('format')),
            'delivery' => ReportDelivery::None,
            'status' => ReportStatus::Pending,
        ]);

        $job = new GenerateReportJob(
            $dto->id,
            $settings->reportQueue ?: 'reports',
            $settings->reportTimeout ? (int) $settings->reportTimeout : null,
            $settings->reportConnection ?: null,
        );
        GenerateReportJob::initTracking($job, $tenant->id, $request->user()->id, 'Report: '.$dto->type);
        dispatch($job);

        return response()->json(['data' => $dto], Response::HTTP_CREATED);
    }
}
