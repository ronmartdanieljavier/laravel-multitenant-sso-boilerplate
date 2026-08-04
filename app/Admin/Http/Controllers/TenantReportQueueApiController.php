<?php

namespace App\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Repositories\Central\ReportRepository;
use Illuminate\Http\JsonResponse;

class TenantReportQueueApiController extends Controller
{
    public function __construct(
        private readonly ReportRepository $reportRepository,
    ) {}

    /**
     * Return paginated report jobs for the given tenant.
     *
     * @param  Tenant  $tenant  the tenant for which to list report jobs
     */
    public function index(Tenant $tenant): JsonResponse
    {
        return response()->json(['data' => $this->reportRepository->listForTenant($tenant->id)]);
    }
}
