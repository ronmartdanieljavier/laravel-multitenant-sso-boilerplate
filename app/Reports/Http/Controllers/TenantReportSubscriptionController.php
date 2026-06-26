<?php

namespace App\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Reports\Http\Requests\StoreReportSubscriptionRequest;
use App\Reports\Services\TenantReportSubscriptionService;
use Illuminate\Http\RedirectResponse;

class TenantReportSubscriptionController extends Controller
{
    public function __construct(
        private readonly TenantReportSubscriptionService $subscriptionService,
    ) {}

    public function store(StoreReportSubscriptionRequest $request): RedirectResponse
    {
        $this->subscriptionService->create($request->validated());

        return back()->with('success', 'Subscription created.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $this->subscriptionService->toggleActive($id);

        return back()->with('success', 'Subscription updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->subscriptionService->delete($id);

        return back()->with('success', 'Subscription deleted.');
    }
}
