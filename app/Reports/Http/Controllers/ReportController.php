<?php

namespace App\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Central\Report;
use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportStatus;
use App\Reports\Http\Requests\StoreBatchReportRequest;
use App\Reports\Http\Requests\StoreReportRequest;
use App\Reports\Jobs\GenerateReportBatchJob;
use App\Reports\Jobs\GenerateReportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reports = Report::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json($reports);
    }

    public function store(StoreReportRequest $request): JsonResponse
    {
        $report = Report::create([
            'user_id' => $request->user()->id,
            'type' => $request->input('type'),
            'format' => ReportFormat::from($request->input('format')),
            'delivery' => ReportDelivery::from($request->input('delivery')),
            'status' => ReportStatus::Pending,
            'parameters' => $request->input('parameters'),
        ]);

        GenerateReportJob::dispatch($report);

        return response()->json($report, Response::HTTP_CREATED);
    }

    public function batch(StoreBatchReportRequest $request, GenerateReportBatchJob $batchJob): JsonResponse
    {
        $batchId = Str::uuid()->toString();
        $user = $request->user();

        [$reports, $batch] = DB::transaction(function () use ($request, $user, $batchId, $batchJob) {
            $reports = collect($request->input('reports'))->map(
                fn (array $item) => Report::create([
                    'user_id' => $user->id,
                    'type' => $item['type'],
                    'format' => ReportFormat::from($item['format']),
                    'delivery' => ReportDelivery::from($item['delivery']),
                    'status' => ReportStatus::Pending,
                    'parameters' => $item['parameters'] ?? null,
                    'batch_id' => $batchId,
                ])
            );

            return [$reports, $batchJob->dispatch($reports, $batchId)];
        });

        return response()->json([
            'batch_id' => $batchId,
            'horizon_batch_id' => $batch->id,
            'report_count' => $reports->count(),
            'reports' => $reports->values(),
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, Report $report): JsonResponse
    {
        Gate::authorize('view', $report);

        $report->loadMissing('user', 'tenant');

        return response()->json($report);
    }

    public function download(Request $request, Report $report): StreamedResponse
    {
        Gate::authorize('download', $report);

        abort_unless(
            $report->status === ReportStatus::Success && $report->file_path !== null,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Report is not ready for download.'
        );

        $storage = Storage::disk();

        abort_unless($storage->exists($report->file_path), Response::HTTP_NOT_FOUND, 'Report file not found.');

        return $storage->download($report->file_path);
    }

    public function destroy(Request $request, Report $report): JsonResponse
    {
        Gate::authorize('delete', $report);

        if ($report->file_path !== null) {
            Storage::delete($report->file_path);
        }

        $report->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
