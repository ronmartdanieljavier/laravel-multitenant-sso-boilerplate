<?php

namespace App\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Repositories\Central\ReportRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantReportQueueApiController extends Controller
{
    public function __construct(
        private readonly ReportRepository $reportRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('current_tenant');

        abort_if($tenant === null, 404);

        $reports = $this->reportRepository->listForTenant($tenant->id);

        return response()->json(['data' => $reports]);
    }
}
