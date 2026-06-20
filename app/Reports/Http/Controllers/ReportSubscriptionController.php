<?php

namespace App\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ReportSubscription;
use App\Reports\Http\Requests\StoreReportSubscriptionRequest;
use App\Reports\Http\Requests\UpdateReportSubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ReportSubscriptionController extends Controller
{
    public function index(): JsonResponse
    {
        $subscriptions = ReportSubscription::on('tenant')
            ->latest()
            ->paginate(20);

        return response()->json($subscriptions);
    }

    public function store(StoreReportSubscriptionRequest $request): JsonResponse
    {
        $subscription = ReportSubscription::on('tenant')->create($request->validated());

        return response()->json($subscription, Response::HTTP_CREATED);
    }

    public function show(int $id): JsonResponse
    {
        $subscription = ReportSubscription::on('tenant')->findOrFail($id);

        Gate::authorize('view', $subscription);

        return response()->json($subscription);
    }

    public function update(UpdateReportSubscriptionRequest $request, int $id): JsonResponse
    {
        $subscription = ReportSubscription::on('tenant')->findOrFail($id);

        Gate::authorize('update', $subscription);

        $subscription->update($request->validated());

        return response()->json($subscription);
    }

    public function destroy(int $id): JsonResponse
    {
        $subscription = ReportSubscription::on('tenant')->findOrFail($id);

        Gate::authorize('delete', $subscription);

        $subscription->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
