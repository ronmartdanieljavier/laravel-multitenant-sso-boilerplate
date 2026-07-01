<?php

namespace App\Reports\Http\Controllers;

use App\Admin\Services\TenantSettingsService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Reports\ReportResource;
use App\Models\Central\Report;
use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportStatus;
use App\Reports\Http\Requests\StoreBatchReportRequest;
use App\Reports\Http\Requests\StoreReportRequest;
use App\Reports\Jobs\GenerateReportBatchJob;
use App\Reports\Jobs\GenerateReportJob;
use App\Repositories\Central\ReportRepository;
use App\Storage\StorageResolver;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private ReportRepository $reportRepository,
        private TenantSettingsService $tenantSettingsService,
        private readonly StorageResolver $resolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->reportRepository->listForUser($request->user()->id)
        );
    }

    public function store(StoreReportRequest $request): JsonResponse
    {
        $dto = $this->reportRepository->create([
            'user_id' => $request->user()->id,
            'type' => $request->input('type'),
            'format' => ReportFormat::from($request->input('format')),
            'delivery' => ReportDelivery::from($request->input('delivery')),
            'status' => ReportStatus::Pending,
            'parameters' => $request->input('parameters'),
        ]);

        [$queue, $timeout, $connection] = $this->resolveReportConfig($request);

        GenerateReportJob::dispatch($dto->id, $queue, $timeout, $connection);

        return response()->json(['data' => $dto], Response::HTTP_CREATED);
    }

    public function batch(StoreBatchReportRequest $request, GenerateReportBatchJob $batchJob): JsonResponse
    {
        $batchId = Str::uuid()->toString();
        $user = $request->user();
        [$queue, $timeout, $connection] = $this->resolveReportConfig($request);

        [$reports, $batch] = DB::transaction(function () use ($request, $user, $batchId, $batchJob, $queue, $timeout, $connection) {
            $reports = collect($request->input('reports'))->map(
                fn (array $item) => $this->reportRepository->create([
                    'user_id' => $user->id,
                    'type' => $item['type'],
                    'format' => ReportFormat::from($item['format']),
                    'delivery' => ReportDelivery::from($item['delivery']),
                    'status' => ReportStatus::Pending,
                    'parameters' => $item['parameters'] ?? null,
                    'batch_id' => $batchId,
                ])
            );

            return [$reports, $batchJob->dispatch($reports, $batchId, $queue, $timeout, $connection)];
        });

        return response()->json([
            'batch_id' => $batchId,
            'horizon_batch_id' => $batch->id,
            'report_count' => $reports->count(),
            'reports' => $reports->values(),
        ], Response::HTTP_CREATED);
    }

    /** @return array{string, int|null, string|null} */
    private function resolveReportConfig(Request $request): array
    {
        $tenant = $request->attributes->get('current_tenant');

        if ($tenant === null) {
            return ['reports', null, null];
        }

        $settings = $this->tenantSettingsService->getSettings($tenant->id);

        return [
            $settings->reportQueue ?: 'reports',
            $settings->reportTimeout ? (int) $settings->reportTimeout : null,
            $settings->reportConnection ?: null,
        ];
    }

    public function show(Request $request, Report $report): ReportResource
    {
        Gate::authorize('view', $report);

        $report->loadMissing('user', 'tenant');

        return new ReportResource($report);
    }

    public function download(Request $request, Report $report): StreamedResponse
    {
        Gate::authorize('download', $report);

        abort_unless(
            $report->status === ReportStatus::Success && $report->file_path !== null,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Report is not ready for download.'
        );

        $storage = $this->diskFor($report);

        abort_unless($storage->exists($report->file_path), Response::HTTP_NOT_FOUND, 'Report file not found.');

        return $storage->download($report->file_path);
    }

    public function destroy(Request $request, Report $report): JsonResponse
    {
        Gate::authorize('delete', $report);

        if ($report->file_path !== null) {
            $this->diskFor($report)->delete($report->file_path);
        }

        $report->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function diskFor(Report $report): FilesystemAdapter
    {
        return $report->tenant_id !== null
            ? $this->resolver->forTenant($report->tenant_id)
            : $this->resolver->forSystem();
    }
}
